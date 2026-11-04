<?php

namespace App\Services\Booking;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Customer;
use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use App\Models\TourBooking;
use App\Models\BookingTransact;
use App\Models\Transaction;
use App\Services\TourPackage\TourPackageService;
use App\Services\Booking\BookingService;
use App\Services\Booking\CustomChargeBookingService;
use App\Services\Booking\TipService;
use App\Services\Booking\TransactionService;
use App\Services\Booking\StripeService;
use App\Utils\BookingUtils;
use Stripe\Checkout\Session;
use Stripe\PaymentIntent;
use Carbon\Carbon;
use App\Services\Cart\CartService;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Services\CardBookingService;
use App\Services\Booking\AddonBookingService;
use App\Services\Booking\EmailService;
use App\Services\ThreeWayPayment\ThreeWayPaymentService;
use App\Services\Payments\PaymentModeResolver;
use App\Services\Payments\PaymentModeInference;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\Booking\HistoryService;
use App\Services\PermitFeeRefundBookingService;
use App\Services\Booking\RemainingPayment\RemainingPaymentService;
use App\Models\TourBookingAddonField;

class PaymentLinkService
{
    /**
     * Store payment link in tour_booking_payment_links table
     *
     * @param integer|string $tourBookingId
     * @param mixed $paymentLinkInfo
     * @param string $slotDateTime
     *
     * @return object
     */
    public static function storeTourBookingPaymentLink($orderId, $paymentLinkInfo, $slotDateTime, $totalAmount = null, bool $overrideExpiry = false): object
    {
        $paymentLink = json_decode($paymentLinkInfo, true);
        $token = self::generateUniqueToken();

        $paymentLinkDays = getSettingsByKey('PAYMENT_LINK_DAYS');
        $daysPaymentLink = (int) $paymentLinkDays->value;
        $expiredDateTime = Carbon::parse($slotDateTime)->subDays($daysPaymentLink);

        // Cancel the old payment link (skip remaining_payment section — those are managed separately)
        $Oldlink = TourBookingPaymentLink::where('order_id', $orderId)
            ->where('status', 'Unpaid')
            ->where(function ($q) {
                $q->whereNull('section')->orWhere('section', '!=', 'remaining_payment');
            })
            ->first();
        if ($Oldlink) {
            TourBookingPaymentLinkItem::where('payment_link_id', $Oldlink->id)
                ->where('status', 'Unpaid')
                ->update(['status' => 'Cancelled', 'updated_at' => Carbon::now()]);
            $Oldlink->status = 'Cancelled';
            $Oldlink->save();
        }

        $overrideExpiryDate = ($overrideExpiry || session('overrideExpiryDate') === 1) ? "Yes" : "No";
        $paymentLinkData = [
            'order_id' => $orderId,
            'amount' => $paymentLink['amount'],
            'reason_of_payment' => $paymentLink['reason_of_payment'],
            'email' => $paymentLink['customer_email'],
            'phone_number' => $paymentLink['customer_phone_no'],
            'token' => $token,
            'status' => 'Unpaid',
            'override_status' => $overrideExpiryDate,
            'expired_at' => $expiredDateTime,
            'created_by' => Auth::id(),
            'created_by_user_type' => userType(),
            'created_at' => now(),
            'updated_at' => now()
        ];

        $createdPaymentLink = TourBookingPaymentLink::create($paymentLinkData);
        session()->forget('overrideExpiryDate');
        session()->save();

        DB::commit();

        return $createdPaymentLink;
    }

    /**
     * Generate a random string to use as a token for payment links
     *
     * @return string
     */
    private static function generateUniqueToken(): string
    {
        do {
            $token = Str::random(64);
        } while (TourBookingPaymentLink::where('token', $token)->exists());

        return $token;
    }

    /**
     * Create Payment Link URL For Customer
     *
     * @return mixed
     */
    public static function createPaymentLink($token): mixed
    {
        $now = Carbon::today();
        $status = TourBookingPaymentLink::where('token', $token)
            ->where('status', 'Unpaid')
            ->first();

        $paymentLink = TourBookingPaymentLink::where('token', $token)->where('status', 'Unpaid');

        if (!empty($status) && $status->override_status == 'No') {
            $paymentLink->where('expired_at', '>', $now);
        }

        $paymentLink = $paymentLink->first();

        if (empty($status) || !empty($status) && $status->override_status == 'No') {
            if (!$paymentLink || $paymentLink->amount <= 0) {
                return view('customer.payment-link-expire'); // TODO: Add proper view
            }
        }

        // Expire the existing Stripe session (if any) then clear local references
        if ($paymentLink->stripe_session_id) {
            try {
                app(PaymentGatewayInterface::class)->expireCheckoutSession($paymentLink->stripe_session_id);
            } catch (\Exception) {
                // Session may already be expired or completed — safe to ignore
            }
            $paymentLink->stripe_session_id  = null;
            $paymentLink->stripe_checkout_url = null;
            $paymentLink->save();
        }

        $tourBooking = TourBooking::where('group_id', $paymentLink->order_id)->first();
        $tourBookingCount = TourBooking::where('group_id', $paymentLink->order_id)->count();
        $userType = userType();
        $bookingInfo = TourPackageService::getTourSlotInfo($tourBooking->tour_slot_id, $userType);

        $transaction = BookingTransact::where('tour_booking_id', $tourBooking->id)
            ->where('deleted_by', 0)
            ->first();

        // Mixed-cart rule: this Checkout Session settles as ONE charge covering every booking
        // sharing this order/group -- a multi-booking cart, a solo booking, or a remaining_payment
        // addon/discount/custom-charge/switch-package top-up on one of them. Any 2027+ tour date
        // among them makes the whole charge platform-held. Resolved fresh here, on every visit,
        // since this Checkout Session rebuilds from scratch each time the link is opened -- never
        // cached from link-creation time.
        $mode = app(PaymentModeResolver::class)->resolve(
            TourBooking::where('group_id', $paymentLink->order_id)
                ->with('tourSlotTime')
                ->get()
                ->map(fn ($b) => $b->tourSlotTime?->date)
        );

        if ($paymentLink->section === 'remaining_payment') {
            // For remaining_payment links the fee for each item is already stored:
            // - change_people / addon items: details.platform_fees
            // - custom_charge items: tour_booking_custom_charges.fees (via reference_id)
            // Summing these avoids reading ALL current pax (which would include pax
            // from previous payments and over-state the application_fee_amount).
            $stripeFees = 0;
            $linkItems  = TourBookingPaymentLinkItem::where('payment_link_id', $paymentLink->id)->get();
            foreach ($linkItems as $linkItem) {
                if ($linkItem->status === 'Cancelled') {
                    continue;
                }
                $itemDetails = is_array($linkItem->details)
                    ? $linkItem->details
                    : (json_decode($linkItem->details ?? '[]', true) ?? []);
                if (isset($itemDetails['platform_fees'])) {
                    $stripeFees += (float) $itemDetails['platform_fees'];
                } elseif ($linkItem->reference_id && $linkItem->type === 'custom_charge') {
                    $chargeRow   = DB::table('tour_booking_custom_charges')->find($linkItem->reference_id);
                    $stripeFees += $chargeRow ? (float) $chargeRow->fees : 0;
                } elseif ($linkItem->reference_id && $linkItem->type === 'discount') {
                    $histRow    = DB::table('tour_booking_history')->find($linkItem->reference_id);
                    $stripeFees += $histRow ? (float) ($histRow->platform_fees ?? 0) : 0;
                }
            }
        } elseif ($tourBookingCount > 1) {
            $tourBookings = TourBooking::where('group_id', $paymentLink->order_id)->get();
            $stripeFees = 0;
            foreach ($tourBookings as $tBooking) {
                $bookingT = BookingTransact::where('tour_booking_id', $tBooking->id)
                    ->where('deleted_by', 0)
                    ->get();
                $natFeesPercentage = $bookingT[0]->nat_fees_percentage;
                $addons = BookingService::fetchPayLaterAddonsWithPrice($tBooking->id, $natFeesPercentage);
                $customCharge = CustomChargeBookingService::customChargePrice($tBooking->id);
                if ($tBooking->discount2_value > 0) {
                    $stripeFees += roundout(($tBooking->total * $natFeesPercentage / 100), 2);
                } else {
                    foreach ($bookingT as $tbTransact) {
                        if ($tbTransact->is_group_rate == '1') {
                            $stripeFees = roundout((($tbTransact->rate + $tbTransact->additional_charge + $tbTransact->partner_fees_amount) * $natFeesPercentage / 100), 2);
                        } else {
                            $serviceCommissionPax = roundout((($tbTransact->rate + $tbTransact->additional_charge + $tbTransact->permit_fee + $tbTransact->partner_fees_amount) * $natFeesPercentage / 100), 2);
                            $serviceCommissionPax = $tbTransact->tourists * $serviceCommissionPax;
                            $stripeFees += $serviceCommissionPax;
                        }
                    }
                }

                $stripeFees += $customCharge['pay_later_custom_charge_fees'] + $addons['addons_fee'];
            }
        } else {
            $bookingT = BookingTransact::where('tour_booking_id', $tourBooking->id)
                ->where('deleted_by', 0)
                ->get();
            $natFeesPercentage = $transaction->nat_fees_percentage;
            $addons = BookingService::fetchPayLaterAddonsWithPrice($tourBooking->id, $natFeesPercentage);
            $customCharge = CustomChargeBookingService::customChargePrice($tourBooking->id);
            $stripeFees = 0;
            if ($tourBooking->discount2_value > 0) {
                $stripeFees = roundout(($tourBooking->total * $natFeesPercentage / 100), 2);
            } else {
                foreach ($bookingT as $tbTransact) {
                    if ($tbTransact->is_group_rate == '1') {
                        $stripeFees = roundout((($tbTransact->rate + $tbTransact->additional_charge + $tbTransact->partner_fees_amount) * $natFeesPercentage / 100), 2);
                    } else {
                        $serviceCommissionPax = roundout((($tbTransact->rate + $tbTransact->additional_charge + $tbTransact->permit_fee + $tbTransact->partner_fees_amount) * $natFeesPercentage / 100), 2);
                        $serviceCommissionPax = $tbTransact->tourists * $serviceCommissionPax;
                        $stripeFees += $serviceCommissionPax;
                    }
                }
            }
           $stripeFees += $customCharge['pay_later_custom_charge_fees'] + $addons['addons_fee'];
        }

        // Format booking date and time
        $bookingDate = Carbon::parse($bookingInfo->date)->format('F j, Y');
        $fromTime = Carbon::parse($bookingInfo->time_from)->format('g:i A');

        // Generate group/tourist details
        $rateGroups = collect(DB::select(
            $tourBooking->is_group_booking
                ? "SELECT GROUP_CONCAT(CONCAT(tbt.tourists,' Person(s)')) AS t_groups
                FROM tour_bookings_transact AS tbt 
                INNER JOIN tour_package_rates AS tpr ON tpr.id = tbt.tour_package_rate_id 
                WHERE tbt.deleted_by = 0 AND tbt.tour_booking_id = ?"
                : "SELECT GROUP_CONCAT(
                        CASE 
                            WHEN tbt.is_infant_rate = 1 THEN CONCAT(tbt.infants, ' ', tpr.rate_for)
                            ELSE CONCAT(tbt.tourists, ' ', tpr.rate_for)
                        END
                        ORDER BY tpr.age_from DESC, tpr.age_to DESC SEPARATOR ' '
                    ) AS t_groups
                FROM tour_bookings_transact AS tbt 
                INNER JOIN tour_package_rates AS tpr ON tpr.id = tbt.tour_package_rate_id 
                WHERE tbt.deleted_by = 0 AND tbt.tour_booking_id = ?",
            [$tourBooking->id]
        ))->first();

        $logo = str_replace(' ', '%20', tourOperatorLogo($bookingInfo->tenant_id, $bookingInfo->tour_op_logo));
        $isMultipleBookings = $tourBookingCount > 1;

        $bookingDescription = $isMultipleBookings
            ? sprintf('Order id #%s', bookingPrefix($paymentLink->order_id))
            : sprintf(
                'Booking #%s | %s @ %s | %s',
                bookingPrefix($tourBooking->id),
                $bookingDate,
                $fromTime,
                $rateGroups->t_groups
            );

        $productName = $isMultipleBookings
            ? $bookingInfo->tour_operator
            : $bookingInfo->package_name . ' at ' . $bookingInfo->tour_operator;

        $productData = [
            'name' => $productName,
            'description' => $bookingDescription,
            'images' => [$logo],
        ];

        $listParams = ['email' => $tourBooking->email];
        $existingCustomers = app(PaymentGatewayInterface::class)->listCustomers($listParams);
        if (count($existingCustomers->data) > 0) {
            $customer = $existingCustomers->data[0];
        } else {
            $newCustomerParams = [
                'email' => $tourBooking->email,
                'name'  => $tourBooking->name,
                'phone' => $tourBooking->phone_number,
            ];
            $customer = app(PaymentGatewayInterface::class)->createCustomer($newCustomerParams);
        }

        $statementDescriptor = tourOperator()->statement_descriptor;

        $paymentIntentData = app(PaymentGatewayInterface::class)->buildConnectedPaymentData(
            $bookingInfo->stripe_destination_id,
            (int) round($stripeFees * 100),
            strtoupper(substr($statementDescriptor, 0, 22)),
            $mode
        );

        // Every other Stripe-charging flow (Make Payment, New Booking, Change People, ...) sets a
        // human-readable description plus a structured metadata.bookings payload directly on the
        // PaymentIntent — see StripeUtils::createStripeIntent(). A Payment Link's PaymentIntent is
        // created by Stripe Checkout under the hood, which never inherits the Checkout Session's
        // OWN top-level metadata (set below, used by savePaymentUpdateBooking()'s reconciliation)
        // — payment_intent_data is the only way to also put description/metadata directly on the
        // resulting PaymentIntent, and without it every Payment Link payment showed up in Stripe
        // with a blank description and no booking metadata at all.
        $paymentIntentMetadata = [
            'booking_id'     => $tourBooking->id,
            'tour_package'   => $bookingInfo->package_name,
            'tour_date'      => $bookingInfo->date,
            'tour_time'      => $bookingInfo->time_from,
            'tourists'       => $tourBooking->tourists + $tourBooking->infants,
            'amount'         => $paymentLink->amount,
            'platform_fee'   => $stripeFees ?? 0.00,
            'partner_fee'    => 0.00,
            'payout_amount'  => 0.00,
            'split_payment'  => 0,
        ];
        $paymentIntentData['description'] = sprintf(
            "Booking Id: %s\nTour Package: %s\nTour Date: %s\nTour Time: %s\nTourists: %s\nEmail: %s",
            $tourBooking->id,
            $bookingInfo->package_name,
            $bookingInfo->date,
            $bookingInfo->time_from,
            $tourBooking->tourists + $tourBooking->infants,
            $tourBooking->email
        );
        $paymentIntentData['metadata'] = [
            'bookings'  => json_encode($paymentIntentMetadata),
            'tenant_id' => tenant()->id,
        ];

        $isAffiliate = Customer::where('email', $tourBooking->email)->where('is_affiliate', 'Yes')->first();
        // Only allow card saving when NOT disabled
        if (hasSetting('DISABLE_CARD_SAVE') == false) {
            if ($isAffiliate && hasSetting('DISABLE_AFFILIATE_CARD_SAVE') == false) {
                $paymentIntentData['setup_future_usage'] = 'off_session';
            } else if(!$isAffiliate) {
                $paymentIntentData['setup_future_usage'] = 'off_session';
            }
        }
        

        $sessionParams = [
            'payment_method_types' => ['card'],
            'customer' => $customer->id,
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower(currencyFormation(code: true)),
                    'product_data' => $productData,
                    'unit_amount' => $paymentLink->amount * 100,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => route('customer.success-payment') . '?session_id={CHECKOUT_SESSION_ID}',
            'payment_intent_data' => stripOnBehalfOfForCountry($paymentIntentData, $mode),
            'metadata' => [
                'booking_id' => $tourBooking->id,
                'tenant' => tenancy()->tenant->name,
                'tenant_id' => tenancy()->tenant->id,
                'payment_id' => $paymentLink->id,
                'customer_email' => $paymentLink->email,
            ],
            'expires_at' => time() + 1800,
        ];
        $session = app(PaymentGatewayInterface::class)->createCheckoutSession($sessionParams);

        // Save session ID and URL for reuse
        $paymentLink->stripe_session_id = $session->id;
        $paymentLink->stripe_checkout_url = $session->url;
        $paymentLink->save();

        return redirect($session->url);
    }

    /**
     * Save Payment Paid Via Customer Through Payment Link After Successful Payment
     *
     * @param object $request
     * 
     * @return mixed
     */
    public static function savePaymentUpdateBooking($request)
    {
        $updatedDateTime = mysqlDT();
        $sessionId = $request->query('session_id');

        if (!$sessionId) {
            abort(404, 'Session ID missing');
        }

        $session = Session::retrieve($sessionId);
        $paymentIntent = PaymentIntent::retrieve($session->payment_intent, [
            'expand' => ['charges']
        ]);

        $bookingId = $session->metadata->booking_id;
        $paymentLinkId = $session->metadata->payment_id;
        HistoryService::getSavedCardFromPaymentIntent($paymentIntent->id, $bookingId, "PAYMENT LINK");

        $tourBooking = TourBooking::find($bookingId);
        $paymentLink = TourBookingPaymentLink::find($paymentLinkId);

        // Remaining-balance payment link — always forks before the combo-payment transaction check
        if ($paymentLink && $paymentLink->section === 'remaining_payment') {
            if ($paymentIntent->status === 'succeeded' && $session->payment_status === 'paid') {
                self::finalizeRemainingPaymentLink($paymentLink, $paymentIntent, $bookingId);
                DB::commit();
            }
            return [
                'bookingID' => $bookingId,
                'cartId'    => $tourBooking->cart_id,
                'payment_method_cart_payment' => 'Card',
                'feature'   => 'remaining_payment',
                'payment_intent_id' => $paymentIntent->id,
            ];
        }

        $transaction = Transaction::where('tour_booking_id', $bookingId)->first();
        if ($transaction) {
            return [
                "bookingID" => $bookingId,
                "cartId" => $tourBooking->cart_id,
                "payment_method_cart_payment" => "Card",
                "feature" => "make_combo_payment",
                'payment_intent_id' => $paymentIntent->id,
            ];
        }

        if ($paymentLink && $paymentIntent->status === 'succeeded' && $session->payment_status === 'paid' && $paymentLink->status !== 'Paid' && empty($transaction)) {
            TourBookingPaymentLinkItem::where('payment_link_id', $paymentLink->id)
                ->where('status', 'Unpaid')
                ->update(['status' => 'Paid', 'updated_at' => $updatedDateTime]);
            $paymentLink->update([
                'status' => 'Paid',
                'updated_at' => $updatedDateTime
            ]);

            $data = [
                "bookingID" => $bookingId,
                "cartId" => $tourBooking->cart_id,
                "payment_method_cart_payment" => "Card",
                "feature" => "make_combo_payment",
                'payment_intent_id' => $paymentIntent->id,
            ];

            $dataObject = (object) $data;
            PaymentLinkService::bookingUpdate($dataObject);
            DB::commit();
            return $data;
        }
    }

    /**
     * Count the age group for combo booking payment link
     *
     * @param array $touristGroups
     * 
     * @return mixed
     */

    public static function ageGroupCount($touristGroups)
    {
        $ageGroupCount = '';
        foreach ($touristGroups as $value) {
            $ageGroupCount = $value;
        }

        return $ageGroupCount;
    }

    /**
     * Sort data array for combo booking payment link
     *
     * @param array $tourPackages
     * @param string $userType
     * 
     * @return mixed
     */

    public static function sortedDataArray($tourPackages, $userType)
    {
        $sortedDataArray = [];
        foreach ($tourPackages as $tourPackage) {
            $slotTime = $tourPackage['slot_time'];

            foreach ($tourPackage['touristAgeGroup'] as $value) {
                if (!isset($sortedDataArray[$slotTime])) {
                    $slotTimes = TourPackageService::getTourSlotInfo($slotTime, $userType);

                    $slotDateTime = Carbon::parse("{$slotTimes->date} {$slotTimes->time_from}");
                    $paymentLinkDays = getSettingsByKey('PAYMENT_LINK_DAYS');
                    $daysPaymentLink = (int) $paymentLinkDays->value;
                    $expiredDateTime = $slotDateTime->subDays($daysPaymentLink);
                    $expiredDate = Carbon::parse($expiredDateTime)->format('F j, Y');
                    $expiredTime = Carbon::parse($expiredDateTime)->format('g:i A');
                    $tourAmount = $tourPackage['total'] + $tourPackage['custom_charge_total'] + $tourPackage['addon_total'];
                    $sortedDataArray[$slotTime] = [
                        'touristAgeGroup'    => '',
                        'total'              => $tourAmount,
                        'tour_package_name'  => $tourPackage['tour_package_name'],
                        'expiredDate'        => $expiredDate,
                        'expiredTime'        => $expiredTime,
                    ];
                }
                $sortedDataArray[$slotTime]['touristAgeGroup'] .= "$value ";
            }
        }

        return $sortedDataArray;
    }

    /**
     * Payment link booking update
     * 
     * @param object $request
     * @return mixed
     */
    public static function bookingUpdate($request)
    {
        $bookingId = $request->bookingID;
        $paymentMethod = $request->payment_method_cart_payment;
        $isCashFees = cashFees();
        $oldBookingId = oldBookingId();

        $cartBookings = self::getCartBookings($request);
        $bookingDetails = TourBooking::findOrFail($bookingId);
        $customer = Customer::withTrashed()->findOrFail($bookingDetails->customer_id);
        // Normally the customer completing this via a public Payment Link (no admin/staff
        // session, so Auth::id() is null) — but check Auth first regardless, in case this ever
        // runs under an authenticated session (e.g. a queue worker/webhook processor
        // authenticated as a system account), so a real admin/staff identity is never dropped.
        $createdBy = Auth::id() ? BookingService::createdUserInfo() : 'AppUser: (Customer)';

        [$cardBookingsData, $totalCartAmount, $totalCartFees] = self::processCartBookings($cartBookings, $customer, $paymentMethod, $isCashFees, $oldBookingId, $request);

        if ($paymentMethod === 'Card') {
            self::handleStripePayment($request, $cardBookingsData, $customer, $createdBy, $cartBookings);
        }

        return $bookingId;
    }

    /**
     * Get the cart and without cart bookings
     * 
     * @param object $request
     * @return mixed
     */
    private static function getCartBookings($request)
    {
        return DB::table('tour_bookings_master AS tbm')
            ->when($request->cartId, fn($q) => $q->where('tbm.cart_id', $request->cartId))
            ->when(!$request->cartId, fn($q) => $q->where('tbm.id', $request->bookingID))
            ->get();
    }

    /**
     * Process bookings update for payment link
     * 
     * @param array $cartBookings
     * @param object $customer
     * @param object $paymentMethod
     * @param bool $isCashFees
     * @param object $oldBookingId
     * @param object $request
     * @return mixed
     */
    private static function processCartBookings($cartBookings, $customer, $paymentMethod, $isCashFees, $oldBookingId, $request)
    {
        $cardBookingsData = [];
        $totalCartAmount = $totalCartFees = 0;

        foreach ($cartBookings as $cartBooking) {
            $bookingId = $cartBooking->id;
            $info = BookingUtils::getBookingDetails($bookingId);

            if ($info->is_reserved_for_affiliate) {
                DB::table('tour_bookings_master')->where('id', $bookingId)->update(['is_reserved_for_affiliate' => 0]);
                HistoryService::storeNoteInHistory("Soft booking has been unblocked when make a payment.", $bookingId);
            }

            $commissionRates = TourPackageService::getCommissionFeesPercentage($request, $customer, $info);

            $bookingTransactions = BookingTransact::with('packageRate')
                ->where('tour_booking_id', $bookingId)
                ->where('deleted_by', 0)
                ->get();

            $calcResult = CartService::calculateTotalAmount(
                $bookingTransactions,
                $paymentMethod,
                $isCashFees,
                $request,
                $info,
                $commissionRates['nat_fees_percentage'],
                $commissionRates['stripe_fees_percentage'],
                true
            );

            $total = $calcResult['total'];
            $serviceCommission = 0;
            $oldDiscountAmt = 0;

            if (!empty($info->discount2_value)) {
                $oldDiscountAmt = $info->discount2_percentage == 0
                    ? $info->discount2_value
                    : round(($total * $info->discount2_percentage) / 100, 2);
                $total -= $oldDiscountAmt;
            }

            $isAffiliate = $customer->is_affiliate === 'Yes';
            $isCardPayment = $paymentMethod === 'Card';
            $isAfterCutoff = $bookingId >= $oldBookingId;

            if ($isCardPayment) {
                if (!$isAffiliate || ($isAffiliate && $isAfterCutoff)) {
                    $serviceCommission = ($oldDiscountAmt > 0)
                        ? roundout(($total * $commissionRates['nat_fees_percentage'] / 100), 2)
                        : $calcResult['serviceCommissionTotal'];
                    $total += $serviceCommission;
                } elseif (!$isAfterCutoff) {
                    $serviceCommission = round($total * 6 / 100, 2);
                    $total += $serviceCommission;
                }
            }

            $addons = BookingService::fetchPayLaterAddonsWithPrice($bookingId, $commissionRates['nat_fees_percentage']);
            $customCharge = CustomChargeBookingService::customChargePrice($bookingId);

            $cardBookingsData[] = array_merge([
                'total' => round($total, 2),
                'discount2_value' => $info->discount2_value,
                'tourists' => $info->tourists,
                'booking_id' => $bookingId,
                'tour_package' => $info->package_name,
                'is_partner_booking' => $info->is_partner_booking,
                'date' => $info->date,
                'time_from' => $info->time_from,
                'subtotal' => round($total - $serviceCommission, 2),
                'serviceCommission' => $serviceCommission,
                'partnerFees' => $calcResult['partnerFeesTotal'],
                'destination' => $info->stripe_destination_id,
                'partner_destination_id' => $info->partnerDestinationId,
                'slot_time' => $info->tour_slot_id,
                'tour_operator_id' => $info->tour_operator_id,
                'statement_descriptor' => $info->statement_descriptor,
                'card_reader_prefix' => $info->card_reader_prefix,
                'tour_package_id' => $info->tour_package_id
            ], $addons, $customCharge);

            $tourBooking = BookingService::bookingInfoData($bookingId);
            $booking = CartService::prepareBookingDataForMakeCartPayment($tourBooking, $bookingId, 'Card', $total);
            CartService::dispatchBooking($booking);

            $totalCartAmount += $total;
            $totalCartFees += $serviceCommission;
        }

        return [$cardBookingsData, $totalCartAmount, $totalCartFees];
    }

    /**
     * Handle the stripe payment
     * 
     * @param object $request
     * @param mixed $cardBookingsData
     * @param object $customer
     * @param object $createdBy
     * @param mixed $cartBookings
     * @return mixed
     */

    private static function handleStripePayment($request, $cardBookingsData, $customer, $createdBy, $cartBookings)
    {
        $paymentIntentId = $request->payment_intent_id;
        if (empty($paymentIntentId)) {
            throw new Exception('You must provide Stripe Payment Intent Id');
        }

        $retrieveOptions = ['expand' => ['charges.data.transfer']];
        $paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment($paymentIntentId, $retrieveOptions);
        $cardDetails = StripeService::extractCardDetails($paymentIntent);

        $metadata = [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone_number,
            'data' => $cardBookingsData,
            'split_value' => '',
            'from' => $cardDetails['source']
        ];

        if (!$request->cartId && $cartBookings[0]->is_partner_booking == 1) {
            $first = $cardBookingsData[0];
            $metadata += [
                'total' => $first['total'] ?? 0.00,
                'serviceCommission' => $first['serviceCommission'] ?? 0.00,
                'partnerFees' => $first['partnerFees'] ?? 0.00,
                'payoutAmount' => $first['subtotal'] - $first['partnerFees'],
                'isPartnerBooking' => $first['is_partner_booking'] ?? 0
            ];
        }

        $stripeMetadata = CartService::stripeMetadata($metadata);
        $updateParams = $stripeMetadata;
        app(PaymentGatewayInterface::class)->updatePayment($paymentIntentId, $updateParams);

        if (!empty($paymentIntent->charges->data[0]->transfer->destination_payment)) {
            $chargeParams = $stripeMetadata;
            app(PaymentGatewayInterface::class)->updateConnectedPayment(
                $paymentIntent->charges->data[0]->transfer->destination_payment,
                $chargeParams,
                $paymentIntent->charges->data[0]->transfer->destination
            );
        }

        foreach ($cardBookingsData as $cardBooking) {
            $bookingId = $cardBooking['booking_id'];
            $exists = Transaction::where('transaction_number', $paymentIntentId)
                ->where('tour_booking_id', $bookingId)
                ->where('status', 'Completed')
                ->exists();

            if (!$exists) {
                $tourBooking = BookingService::bookingInfoData($bookingId);
                $transactionId = TransactionService::storePaymentInTransaction(
                    $bookingId,
                    $cardBooking['total'],
                    $cardBooking['serviceCommission'],
                    'Card',
                    0,
                    $paymentIntentId,
                    $paymentIntent->charges->data[0]->application_fee ?? null,
                    ['booked_by_staff' => $tourBooking[0]->created_by, 'booked_by_staff_type' => $tourBooking[0]->created_by_user_type],
                    mode: PaymentModeInference::fromStripeObject($paymentIntent)
                );

                if ($cardBooking['is_partner_booking'] == 1) {
                    ThreeWayPaymentService::transferToPartner([
                        'amount' => $cardBooking['total'],
                        'platform_fees' => $cardBooking['serviceCommission'],
                        'partner_fees' => $cardBooking['partnerFees'],
                        'booking_id' => $bookingId,
                        'transaction_id' => $transactionId,
                        'package_id' => $cardBooking['tour_package_id'],
                        'partner_destination_id' => $cardBooking['partner_destination_id'],
                        'transfer_group' => $paymentIntent->transfer_group,
                        'payment_method' => 'Card'
                    ]);
                }

                $historyId = HistoryService::storeCardPaymentInHistory(
                    $tourBooking[0]->booking_id,
                    $tourBooking[0]->tourists + $tourBooking[0]->infants,
                    [],
                    $cardDetails['last4'],
                    $cardDetails['brand'],
                    $createdBy,
                    $cardBooking,
                    'Manual Update Make Payment - Payment Link'
                );

                $amountUpdate = TourBooking::findOrFail($bookingId);
                $amountUpdate->update([
                    'total' => $cardBooking['total'],
                    'booking_total' => $cardBooking['total'],
                    'service_commission' => $cardBooking['serviceCommission']
                ]);

                $tourBooking[0]->tour_operator_id = $cardBooking['tour_operator_id'];
                CardBookingService::run((array) $tourBooking[0], $transactionId, $historyId, 'Full Payment', (array) $request, $paymentIntent);
                AddonBookingService::createAddonTransactionsFromFields($bookingId, $paymentIntent, 'card', true);
                DB::commit();
            }
        }
    }


    /**
     * 
     * @param mixed $tourBooking
     * @return mixed
     */
    public static function arEmailDataPaymentLink($tourBooking)
    {
        $info = DB::table('tour_bookings_master AS tbm')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
            ->where('tbm.id', $tourBooking->id)
            ->select([
                'tp.tour_operator_id',
                'tbm.tour_package_id',
                'tp.name AS package_name',
                't_o.name AS tour_operator',
                't_o.email AS tour_op_email',
                't_o.phone_number AS tour_op_phone',
                't_o.website AS tour_op_website',
                'tbm.name',
                't_o.logo AS tour_op_logo',
                't_o.tenant_id',
                'tp.email as packageEmail',
                'tp.from_name as packageFromName',
                'tp.from_email as packageFromEmail',
                'tbm.id'

            ])->first();

        return $info;
    }

    /**
     * Payment link update and email service to send payment link email
     *
     * @param array $data
     * @param mixed $info
     * @param mixed $tourBooking
     * @param mixed $paymentLinkData
     * @param string $moduleName
     *
     * @return mixed
     */
    public static function paymentLinkUpdate($data, $info, $tourBooking = null, $paymentLinkData, $moduleName)
    {
        $payLinkFeeTotal = 0;
        $payLinkSubTotal = 0;
        $payLinkTotal = 0;
        $bookingCount = TourBooking::where('group_id', $tourBooking->group_id)->count();

        if ($moduleName == "Delete People" || $moduleName == "Charge People" || $moduleName == "Permit Fee Remove") {
            $payLinkFeeTotal = roundout(($data['amount'] * $data['natFeesPercentage'] / 100), 2);
            $payLinkSubTotal = $data['amount'] + $payLinkFeeTotal;

            if ($moduleName == "Permit Fee Remove") {
                $payLinkTotal = $paymentLinkData->amount - $data['amount'];
            }

            if ($moduleName == "Delete People") {
                $payLinkTotal = $paymentLinkData->amount - $payLinkSubTotal;
            }

            if ($moduleName == "Charge People") {
                $payLinkTotal = $paymentLinkData->amount + $payLinkSubTotal;
            }
        }

        if ($moduleName == "Edit Discount") {
            if ($data['remainingDiscountValue'] < 0) {
                $discount = $data['remainingDiscountValue'] - $data['fees'];
            } else {
                $discount = $data['remainingDiscountValue'] + $data['fees'];
            }

            $payLinkTotal = $paymentLinkData->amount + $discount;
        }

        if ($moduleName == "Add Custom Charge") {
            $payLinkTotal = $paymentLinkData->amount + $data['amount'] + $data['fees'];
        }

        if ($moduleName == "Refund Custom Charge") {
            $payLinkTotal = $paymentLinkData->amount - ($data['amount'] + $data['fees']);
        }

        if ($moduleName == "Cancel Booking") {
            if ($bookingCount > 1) {
                $payLinkFeeTotal = roundout($data['amount'] * $data['natFeesPercentage'] / 100, 2);
                $payLinkSubTotal = $data['amount'] + $payLinkFeeTotal;

                $payLinkTotal = $paymentLinkData->amount - $payLinkSubTotal;
            }
        }

        if ($moduleName == "Switch Tour Package") {
            $payLinkTotal = $paymentLinkData->amount + ($data['amount'] - $data['oldTotalAmount']);
        }

        if ($moduleName == "Add-ons") {
            if ($data['oldAddonsTotal'] > $data['newAddonsTotal']) {
                $payLinkTotal = $paymentLinkData->amount - ($data['oldAddonsTotal'] - $data['newAddonsTotal']);
            } else {
                $payLinkTotal = $paymentLinkData->amount + ($data['newAddonsTotal'] - $data['oldAddonsTotal']);
            }
        }

        $sendPaymentLinkEmailDisable = false;
        if ($bookingCount == 1 && $moduleName == "Cancel Booking") {
            $sendPaymentLinkEmailDisable = true;
        }

        TourBookingPaymentLinkItem::where('payment_link_id', $paymentLinkData->id)
            ->where('status', 'Unpaid')
            ->update(['status' => 'Expired', 'updated_at' => Carbon::now()]);
        $paymentLinkData->status = 'Expired';
        $paymentLinkData->save();

        // Create new payment link details
        $token = self::generateUniqueToken();
        $newPaymentLinkData = [
            'order_id' => $tourBooking->group_id,
            'amount' => $payLinkTotal,
            'reason_of_payment' => $paymentLinkData->reason_of_payment,
            'email' => $tourBooking->email,
            'phone_number' => $tourBooking->phone_number,
            'token' => $token,
            'status' => 'Unpaid',
            'override_status' => $paymentLinkData->override_status,
            'expired_at' => $paymentLinkData->expired_at,
            'created_by' => Auth::id(),
            'created_by_user_type' => userType(),
            'created_at' => now(),
            'updated_at' => now()
        ];

        $createdPaymentLink = TourBookingPaymentLink::create($newPaymentLinkData);

        // Send payment link to the customer.
        if ($sendPaymentLinkEmailDisable ==  false) {
            EmailService::sendPaymentLinkEmail($info, $tourBooking, $createdPaymentLink);
        }
    }

    /**
     * Split booking payment link update and create new for new booking
     *
     * @param mixed $tourBooking
     * @param mixed $oldTourBooking
     * @param mixed $oldPaymentLink
     * @param mixed $info
     * @param mixed $bookingTransact
     *
     * @return mixed
     */
    public static function splitBookingPaymentLink($tourBooking, $oldTourBooking, $oldPaymentLink, $info, $bookingTransact)
    {
        $userType = userType();
        $slotTime = TourPackageService::getTourSlotInfo($tourBooking->tour_slot_id, $userType);
        $slotDateTime = Carbon::parse("{$slotTime->date} {$slotTime->time_from}");
        $payLinkFeeTotal = roundout(($tourBooking->total * $bookingTransact->nat_fees_percentage) / 100, 2);

        // tour_bookings_master.total never includes pay-later add-ons (see
        // handleMakePaymentSection()'s Branch 2, which adds this same component separately too) —
        // AddonBookingService::splitBookingPaxAddons() (already run by the time this runs) only
        // re-points a Pay Later booking's tour_booking_addon_fields rows, it never touches `total`.
        // Omitting this here silently left the new booking's own add-on share stuck attributed to
        // the OLD booking's link instead (splitOldBookingPaymentLink() below derives its amount by
        // subtracting the new booking's share from the original combined total).
        $addonsAmount = BookingService::fetchPayLaterAddonsWithPrice($tourBooking->id, $bookingTransact->nat_fees_percentage);
        $payLinkTotal = $tourBooking->total + $payLinkFeeTotal + ($addonsAmount['addons_total'] ?? 0);

        // New booking payment link
        PaymentLinkService::splitNewBookingPaymentLink($tourBooking, $oldPaymentLink, $info, $payLinkTotal, $slotDateTime,);

        // Old booking payment link
        PaymentLinkService::splitOldBookingPaymentLink($oldTourBooking, $oldPaymentLink, $info, $payLinkTotal, $slotDateTime);
    }

    /**
     * Split new booking payment link create and send
     *
     * @param mixed $tourBooking
     * @param mixed $oldPaymentLink
     * @param mixed $info
     * @param mixed $payLinkTotal
     * @param mixed $slotDateTime
     *
     */
    public static function splitNewBookingPaymentLink($tourBooking, $oldPaymentLink, $info, $payLinkTotal, $slotDateTime)
    {
        if ($oldPaymentLink->override_status == "Yes") {
            session()->put('overrideExpiryDate', 1);
        }

        // Prepare payment link data
        $paymentLinkData = [
            'amount'             => $payLinkTotal,
            'reason_of_payment'  => '',
            'customer_email'     => $tourBooking->email,
            'customer_phone_no'  => $tourBooking->phone_number,
        ];

        $paymentLinkDataJson = json_encode($paymentLinkData);
        $paymentLink = PaymentLinkService::storeTourBookingPaymentLink($tourBooking->group_id, $paymentLinkDataJson, $slotDateTime);

        // Send payment link email
        EmailService::sendPaymentLinkEmail($info, $tourBooking, $paymentLink);
    }

    /**
     * Split old booking payment link update and send
     *
     * @param mixed $tourBooking
     * @param mixed $oldPaymentLink
     * @param mixed $info
     * @param mixed $payLinkTotal
     * @param mixed $slotDateTime
     *
     */
    public static function splitOldBookingPaymentLink($tourBooking, $oldPaymentLink, $info, $amount, $slotDateTime)
    {
        if ($oldPaymentLink->override_status == "Yes") {
            session()->put('overrideExpiryDate', 1);
        }

        $payLinkTotal = $oldPaymentLink->amount - $amount;

        // Prepare payment link data
        $paymentLinkData = [
            'amount'             => $payLinkTotal,
            'reason_of_payment'  => $oldPaymentLink->reason_of_payment,
            'customer_email'     => $tourBooking->email,
            'customer_phone_no'  => $tourBooking->phone_number,
        ];

        $paymentLinkDataJson = json_encode($paymentLinkData);
        $paymentLink = PaymentLinkService::storeTourBookingPaymentLink($oldPaymentLink->order_id, $paymentLinkDataJson, $slotDateTime);

        // Send payment link email
        EmailService::sendPaymentLinkEmail($info, $tourBooking, $paymentLink);
    }

    /**
     * Save Payment Paid Via Customer Through Payment Link After Successful Payment
     *
     * @param object $request
     * 
     * @return void
     */
    public static function saveTipPayment($request)
    {
        $updatedDateTime = mysqlDT();
        $sessionId = $request->query('session_id');

        if (!$sessionId) {
            abort(404, 'Session ID missing');
        }

        $session = Session::retrieve($sessionId);
        $paymentIntent = PaymentIntent::retrieve($session->payment_intent, [
            'expand' => ['charges']
        ]);

        $guideId = $session->metadata->guide_id != 0 ? $session->metadata->guide_id : null;
        $bookingId = $session->metadata->booking_id;
        $customerId = $session->metadata->customer_id;
        $tipAmount = $session->metadata->tip_amount;
        $tipFees = $session->metadata->tip_fees;
        if ($paymentIntent->status === 'succeeded' && $session->payment_status === 'paid') {

            $gratuityCount = DB::table('gratuities')
                ->where('transaction_number', $paymentIntent->id)
                ->count();
            if ($gratuityCount > 0) {
                throw new Exception('This payment has already been processed.');
            }
            DB::table('gratuities')->insertGetId([
                'booking_id' => $bookingId,
                'customer_id' => $customerId,
                'gratuity_setting_id' => NULL,
                'guide_id' => $guideId,
                'amount' => $tipAmount,
                'fees' => $tipFees,
                'transaction_number' => $paymentIntent->id ?? null,
                'application_fee_id' => $paymentIntent->charges->data[0]->application_fee ?? null,
                'gratuity_type' => 'direct',
                'payment_method' => 'Card',
                'message' => $session->metadata->message ?? '',
                'status' => 'paid',
                'created_at' => $updatedDateTime,
                'updated_at' => $updatedDateTime,
                'created_by_user_type' => 'Customer'
            ]);
            DB::commit();
            if ($bookingId != '') {
                $assignedTourGuides = DB::table('booking_tour_guides')
                    ->where('booking_id', $bookingId)
                    ->get();
                if ($assignedTourGuides) {
                    // Update distribution amounts after saving tour guide
                    if ($tipAmount > 0) {
                        // Equal distribution among all guides
                        $distributionPercentage = roundout(100 / count($assignedTourGuides), 2);
                        $distributionAmount = roundout($tipAmount / count($assignedTourGuides), 2);

                        foreach ($assignedTourGuides as $guide) {
                            DB::table('booking_tour_guides')
                                ->where('id', $guide->id)
                                ->update([
                                    'distribution_percentage' => $distributionPercentage,
                                    'distribution_amount' => $distributionAmount,
                                    'updated_at' => Carbon::now()
                                ]);
                        }
                    }
                }
                $tipDetails = [
                    'tip_type' => 'direct',
                    'tip_amount' => $tipAmount,
                    'tip_fees' => $tipFees
                ];
                $paymentMethod = 'Card';

                $tipTransactionId = TransactionService::storeTipInTransaction($bookingId, $tipAmount, $tipFees, $paymentMethod, $paymentIntent);

                HistoryService::storeTipInHistory($bookingId, $paymentMethod, $tipAmount, $tipFees, $tipDetails, $paymentIntent, $tipTransactionId);
                HistoryService::getSavedCardFromPaymentIntent($paymentIntent->id, $bookingId, "PAYMENT LINK");

                DB::commit();
            }

            DB::commit();
        }
    }

    /**
     * Process all items on a remaining_payment link after Stripe confirms payment.
     * Idempotent — skips items already marked Paid.
     */
    public static function finalizeRemainingPaymentLink( TourBookingPaymentLink $paymentLink, object $paymentIntent, $bookingId, string $paymentMethod = 'Card'): void {
        RemainingPaymentService::finalizeItems($paymentLink, $paymentIntent, $bookingId, $paymentMethod);
    }

    /**
     * Returns the pending remaining_payment link and its items for a booking.
     * Delegates to RemainingPaymentService::getPendingForBooking.
     */
    public static function getPendingForBooking(int|string $bookingId): ?array
    {
        return RemainingPaymentService::getPendingForBooking($bookingId);
    }

    /**
     * Find the booking's pending LUMP-SUM link (section=NULL or anything other than
     * 'remaining_payment') — i.e. explicitly excluding an itemized remaining_payment link.
     *
     * Several call sites used to look up "any Unpaid link for this order" without this
     * exclusion, relying on an unenforced invariant that an itemized link could never
     * coexist with the lookup's intent. When it did coexist, feeding the itemized link into
     * paymentLinkUpdate() (which always fully expires+reissues as a fresh lump-sum link)
     * silently destroyed its item-level detail. This mirrors the exclusion already proven
     * correct in upsertRemainingPaymentLink() above.
     */
    public static function findLumpSumLink(string $orderId): ?TourBookingPaymentLink
    {
        return TourBookingPaymentLink::where('order_id', $orderId)
            ->where('status', 'Unpaid')
            ->where(function ($q) {
                $q->whereNull('section')->orWhere('section', '!=', 'remaining_payment');
            })
            ->first();
    }

    /**
     * Create or update a "remaining_payment" payment link for a Card-paid booking.
     * If an Unpaid remaining_payment link already exists for the order, a new item row
     * is appended and the link total/token are refreshed. Otherwise a fresh link is created.
     */
    public static function upsertRemainingPaymentLink(
        string $orderId,
        array $newItem,
        object $tourBooking,
        Carbon $slotDateTime,
        object $info
    ): void {
        $existing = TourBookingPaymentLink::where('order_id', $orderId)
            ->where('status', 'Unpaid')
            ->where('section', 'remaining_payment')
            ->first();

        if (!$existing) {
            // A booking's only pending balance may already exist as a lump-sum link
            // (section=NULL — created at booking creation, or by a prior paymentLinkUpdate()
            // replacement) rather than an itemized 'remaining_payment' one. Ignoring it here
            // would leave it dangling, Unpaid, while a SECOND, parallel link for only this new
            // item gets created below -- the customer would then owe on two disjoint links
            // instead of one correct combined total. Fold this item's amount into that lump-sum
            // link instead, reusing the same recompute-and-replace mechanism
            // syncPayLaterAddonsPaymentLink() already relies on for this exact scenario.
            $priorLumpSumLink = TourBookingPaymentLink::where('order_id', $orderId)
                ->where('status', 'Unpaid')
                ->where(function ($q) {
                    $q->whereNull('section')->orWhere('section', '!=', 'remaining_payment');
                })
                ->first();

            if ($priorLumpSumLink) {
                self::paymentLinkUpdate(
                    ['oldAddonsTotal' => 0, 'newAddonsTotal' => $newItem['amount']],
                    $info,
                    $tourBooking,
                    $priorLumpSumLink,
                    'Add-ons'
                );
                return;
            }
        }

        if ($existing) {
            $userType = function_exists('userType') ? userType() : 'Admin';
            TourBookingPaymentLinkItem::create([
                'payment_link_id'      => $existing->id,
                'type'                 => $newItem['type'],
                'reference_id'         => $newItem['reference_id'] ?? null,
                'amount'               => $newItem['amount'],
                'status'               => 'Unpaid',
                'details'              => $newItem['details'] ?? null,
                'created_by'           => Auth::id(),
                'created_by_user_type' => $userType,
                'updated_by'           => Auth::id(),
                'updated_by_user_type' => $userType,
            ]);

            $existing->amount          = TourBookingPaymentLinkItem::where('payment_link_id', $existing->id)->where('status', 'Unpaid')->sum('amount');
            $existing->token           = self::generateUniqueToken();
            $existing->override_status = 'Yes';
            $existing->save();

            EmailService::sendPaymentLinkEmail($info, $tourBooking, $existing);
            return;
        }

        $link = TourBookingPaymentLink::create([
            'order_id'             => $orderId,
            'section'              => 'remaining_payment',
            'amount'               => $newItem['amount'],
            'reason_of_payment'    => $newItem['reason'] ?? 'Additional payment for your booking',
            'email'                => $tourBooking->email,
            'phone_number'         => $tourBooking->phone_number,
            'token'                => self::generateUniqueToken(),
            'status'               => 'Unpaid',
            'override_status'      => 'Yes',
            'expired_at'           => Carbon::parse($slotDateTime),
            'created_by'           => Auth::id(),
            'created_by_user_type' => userType(),
        ]);

        $userType = function_exists('userType') ? userType() : 'Admin';
        TourBookingPaymentLinkItem::create([
            'payment_link_id'      => $link->id,
            'type'                 => $newItem['type'],
            'reference_id'         => $newItem['reference_id'] ?? null,
            'amount'               => $newItem['amount'],
            'status'               => 'Unpaid',
            'details'              => $newItem['details'] ?? null,
            'created_by'           => Auth::id(),
            'created_by_user_type' => $userType,
            'updated_by'           => Auth::id(),
            'updated_by_user_type' => $userType,
        ]);

        EmailService::sendPaymentLinkEmail($info, $tourBooking, $link);
    }

    /**
     * Build a [tour_package_rate_id => unpaid_count] map from comma-separated pending
     * payment-link item IDs and/or legacy history IDs submitted with a Change People form.
     *
     * @param  string|null $pendingItemIds    comma-separated TourBookingPaymentLinkItem IDs
     * @param  string|null $pendingHistoryIds comma-separated tour_booking_history IDs
     * @return array<int,int>
     */
    public static function unpaidCountByRateGroupFromFormIds(
        ?string $pendingItemIds,
        ?string $pendingHistoryIds
    ): array {
        $map = [];

        if (!empty($pendingItemIds)) {
            $ids = array_unique(array_filter(array_map('intval', explode(',', $pendingItemIds))));
            foreach ($ids as $piId) {
                $plItem = TourBookingPaymentLinkItem::find($piId);
                if (!$plItem) continue;
                $details = is_array($plItem->details)
                    ? $plItem->details
                    : (json_decode($plItem->details ?? '[]', true) ?? []);
                foreach ($details['added_transact_data'] ?? [] as $td) {
                    $rgId = DB::table('tour_bookings_transact')
                        ->where('id', $td['transact_id'])
                        ->value('tour_package_rate_id');
                    if ($rgId) {
                        $map[$rgId] = ($map[$rgId] ?? 0) + (int)$td['added_count'];
                    }
                }
            }
        }

        if (!empty($pendingHistoryIds)) {
            $ids = array_filter(array_map('intval', explode(',', $pendingHistoryIds)));
            foreach ($ids as $phId) {
                $raw = DB::table('tour_booking_history')->where('id', $phId)->value('details');
                $phData = $raw ? (json_decode($raw, true) ?? []) : [];
                foreach ($phData['added_transact_data'] ?? [] as $td) {
                    $rgId = DB::table('tour_bookings_transact')
                        ->where('id', $td['transact_id'])
                        ->value('tour_package_rate_id');
                    if ($rgId) {
                        $map[$rgId] = ($map[$rgId] ?? 0) + (int)$td['added_count'];
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Cancel pending payment-link items (and their parent link if it becomes empty) for
     * change-people deletions that include unpaid pax.
     *
     * Handles both the new path (TourBookingPaymentLinkItem IDs) and the legacy path
     * (tour_booking_history IDs → linked item).
     *
     * @param  string|null $pendingItemIds    comma-separated TourBookingPaymentLinkItem IDs
     * @param  string|null $pendingHistoryIds comma-separated tour_booking_history IDs
     * @param  bool $skipTotalUpdate         true when the caller's own code already decremented
     *             tour_bookings_master.total for this item (avoids double-decrementing).
     * @param  bool $skipBookingTotalUpdate  true when the caller's own code already decremented
     *             tour_bookings_master.booking_total for this item. Independent of
     *             $skipTotalUpdate: some callers' own pre-update loops touch total but never
     *             booking_total (several do, by an old — now incorrect — assumption that
     *             booking_total is never bumped for an unpaid pax; it IS bumped, together with
     *             total, whenever a Payment-Link change_people item is first created), while
     *             others decrement both total and booking_total together before calling this.
     */
    public static function cancelPendingChangePeopleItems(
        ?string $pendingItemIds,
        ?string $pendingHistoryIds,
        bool $skipTotalUpdate = false,
        bool $skipBookingTotalUpdate = false
    ): void {
        if (!empty($pendingItemIds)) {
            // Parse entries and group by item ID.
            // Supported formats (all backward-compatible):
            //   "itemId:transactId:count" — precise per-transact-row removal (current)
            //   "itemId:count"            — proportional removal (previous, fallback)
            //   "itemId"                  — full delete (legacy)
            //
            // $itemRemovals[itemId] = null means full delete.
            // $itemRemovals[itemId] = [{transact_id, count}] means partial per-transact removal.
            $itemRemovals = [];
            foreach (array_filter(explode(',', $pendingItemIds)) as $entry) {
                $parts = explode(':', trim($entry));
                $piId  = (int) $parts[0];
                if ($piId <= 0) continue;

                if (count($parts) === 3) {
                    // "itemId:transactId:count" — precise format
                    if (!isset($itemRemovals[$piId])) $itemRemovals[$piId] = [];
                    if ($itemRemovals[$piId] !== null) {
                        $itemRemovals[$piId][] = ['transact_id' => (int) $parts[1], 'count' => (int) $parts[2]];
                    }
                } elseif (count($parts) === 2) {
                    // "itemId:count" — proportional fallback (transact_id unknown, mark as 0)
                    if (!array_key_exists($piId, $itemRemovals)) {
                        $itemRemovals[$piId] = [['transact_id' => 0, 'count' => (int) $parts[1]]];
                    }
                } else {
                    // "itemId" — legacy full delete
                    $itemRemovals[$piId] = null;
                }
            }

            foreach ($itemRemovals as $piId => $removals) {
                $linkItem = TourBookingPaymentLinkItem::find($piId);
                if (!$linkItem) continue;

                $details    = is_array($linkItem->details)
                    ? $linkItem->details
                    : (json_decode($linkItem->details ?? '[]', true) ?? []);
                $itemAmount = (float) $linkItem->amount;
                $itemFees   = (float) ($details['platform_fees'] ?? 0);
                if ($itemFees === 0.0 && isset($details['ticket_cost'])) {
                    $itemFees = round(max(0.0, $itemAmount - (float) $details['ticket_cost']), 2);
                }

                $totalPaxInItem = 0;
                foreach ($details['added_transact_data'] ?? [] as $td) {
                    $totalPaxInItem += (int) $td['added_count'];
                }

                // Decide full vs partial
                $totalRemovedPax = 0;
                if ($removals !== null) {
                    foreach ($removals as $r) $totalRemovedPax += $r['count'];
                }
                $isFullRemoval = ($removals === null || $totalPaxInItem <= 0 || $totalRemovedPax >= $totalPaxInItem);

                if ($isFullRemoval) {
                    // ── Full removal ────────────────────────────────────────────────────
                    if ($linkItem->reference_id === null) {
                        $bookingId = $details['tour_booking_id'] ?? null;
                        if ($bookingId && $itemAmount > 0) {
                            $masterUpdate = ['updated_at' => Carbon::now()];
                            if (!$skipTotalUpdate) {
                                $masterUpdate['total'] = DB::raw('total-' . $itemAmount);
                            }
                            // booking_total WAS incremented for this item when the Payment-Link
                            // change_people item was created (changePeopleBooking()'s real-settlement
                            // branch bumps total and booking_total by the same amount regardless of
                            // whether the pax is actually paid yet) — so it must be decremented here
                            // too, or it drifts permanently stale/overstated once the item is cancelled.
                            // Gated independently of $skipTotalUpdate: some callers' own pre-update
                            // loops already decrement booking_total alongside total (then pass
                            // $skipBookingTotalUpdate=true too); others only ever touch total, by an
                            // old assumption that booking_total is never bumped for an unpaid pax.
                            if (!$skipBookingTotalUpdate) {
                                $masterUpdate['booking_total'] = DB::raw('booking_total-' . $itemAmount);
                            }
                            if ($itemFees > 0 && empty($details['fee_deferred'])) {
                                $masterUpdate['service_commission'] = DB::raw('service_commission-' . $itemFees);
                            }
                            DB::table('tour_bookings_master')
                                ->where('id', $bookingId)
                                ->update($masterUpdate);
                        }
                    }
                    $paymentLinkId                  = $linkItem->payment_link_id;
                    $linkItem->status               = 'Cancelled';
                    $linkItem->updated_by           = Auth::id();
                    $linkItem->updated_by_user_type = function_exists('userType') ? userType() : 'Admin';
                    $linkItem->save();
                    self::recalculateRemainingPaymentLink($paymentLinkId);

                } else {
                    // ── Partial removal ─────────────────────────────────────────────────
                    $removedAmount     = 0.0;
                    $removedFees       = 0.0;  // Stripe/state-tax fees from transact (often 0 for NAT)
                    $removedTicketCost = 0.0;
                    $removedNatFees    = 0.0;  // NAT platform_fees portion (proportional)

                    // Build transact_id → removed_count map
                    $removalByTransactId = [];
                    foreach ($removals as $r) {
                        if ($r['transact_id'] > 0) {
                            $removalByTransactId[$r['transact_id']] = ($removalByTransactId[$r['transact_id']] ?? 0) + $r['count'];
                        }
                    }

                    if (!empty($removalByTransactId)) {
                        // Precise path: look up exact rate per transact row
                        $transactRows = DB::table('tour_bookings_transact')
                            ->whereIn('id', array_keys($removalByTransactId))
                            ->get(['id', 'rate', 'additional_charge', 'permit_fee', 'partner_fees_amount',
                                   'additional_tax_percentage', 'processing_charge_percentage'])
                            ->keyBy('id');

                        foreach ($removalByTransactId as $transactId => $removedCount) {
                            $tRow = $transactRows[$transactId] ?? null;
                            if (!$tRow) continue;
                            $rate       = $tRow->rate + $tRow->additional_charge + $tRow->permit_fee + $tRow->partner_fees_amount;
                            $processFee = round($rate * $tRow->processing_charge_percentage / 100, 2);
                            $addlTax    = round($rate * $tRow->additional_tax_percentage / 100, 2);
                            $perPaxFee  = $processFee + $addlTax;
                            $perPaxAmt  = $rate + $perPaxFee;
                            $removedAmount += round($removedCount * $perPaxAmt, 2);
                            $removedFees   += round($removedCount * $perPaxFee, 2);
                        }
                        $removedAmount = round($removedAmount, 2);
                        $removedFees   = round($removedFees, 2);

                        // $removedFees above is the Stripe/state-tax portion — NOT the NAT
                        // platform_fees stored in the item. Compute NAT fees proportionally
                        // using the existing ticket_cost ratio so platform_fees shrinks correctly.
                        $existingTicketCost = (float)($details['ticket_cost'] ?? 0);
                        $removedTicketCost  = max(0.0, $removedAmount - $removedFees);
                        $removedNatFees     = ($existingTicketCost > 0 && $itemFees > 0)
                            ? round($removedTicketCost * ($itemFees / $existingTicketCost), 2)
                            : 0.0;
                    } else {
                        // Proportional fallback (old "itemId:count" format).
                        // Here $itemFees IS the NAT platform_fees, so $removedFees is already correct.
                        $ratio             = $totalPaxInItem > 0 ? $totalRemovedPax / $totalPaxInItem : 1;
                        $removedAmount     = round($itemAmount * $ratio, 2);
                        $removedFees       = round($itemFees * $ratio, 2);
                        $removedTicketCost = max(0.0, $removedAmount - $removedFees);
                        $removedNatFees    = $removedFees;
                    }

                    $totalRemovedFromItem = round($removedTicketCost + $removedNatFees, 2);

                    // Reverse only the removed portion from tour_bookings_master.
                    // booking_total WAS incremented for this item when it was created (see the
                    // matching full-removal branch above) — decrement it here too, proportionally,
                    // or it drifts permanently stale/overstated. Gated independently of
                    // $skipTotalUpdate — see this method's docblock.
                    if ($linkItem->reference_id === null) {
                        $bookingId = $details['tour_booking_id'] ?? null;
                        if ($bookingId && $totalRemovedFromItem > 0) {
                            $masterUpdate = ['updated_at' => Carbon::now()];
                            if (!$skipTotalUpdate) {
                                $masterUpdate['total'] = DB::raw('total-' . $totalRemovedFromItem);
                            }
                            if (!$skipBookingTotalUpdate) {
                                $masterUpdate['booking_total'] = DB::raw('booking_total-' . $totalRemovedFromItem);
                            }
                            if ($removedNatFees > 0 && empty($details['fee_deferred'])) {
                                $masterUpdate['service_commission'] = DB::raw('service_commission-' . $removedNatFees);
                            }
                            DB::table('tour_bookings_master')
                                ->where('id', $bookingId)
                                ->update($masterUpdate);
                        }
                    }

                    // Update added_transact_data — remove or reduce the specific transact rows
                    $updatedTransactData = [];
                    foreach ($details['added_transact_data'] ?? [] as $td) {
                        $tid       = (int) $td['transact_id'];
                        $removeCnt = $removalByTransactId[$tid] ?? 0;
                        $remaining = max(0, (int) $td['added_count'] - $removeCnt);
                        if ($remaining > 0) {
                            $td['added_count']     = $remaining;
                            $updatedTransactData[] = $td;
                        }
                    }

                    $details['added_transact_data'] = $updatedTransactData;
                    $details['ticket_cost']         = max(0, round((float)($details['ticket_cost'] ?? 0) - $removedTicketCost, 2));
                    $details['platform_fees']       = max(0, round($itemFees - $removedNatFees, 2));

                    $linkItem->amount               = max(0, round($itemAmount - $totalRemovedFromItem, 2));
                    $linkItem->details              = json_encode($details, JSON_NUMERIC_CHECK);
                    $linkItem->updated_by           = Auth::id();
                    $linkItem->updated_by_user_type = function_exists('userType') ? userType() : 'Admin';
                    $linkItem->save();

                    self::recalculateRemainingPaymentLink($linkItem->payment_link_id);
                }
            }
        }

        if (!empty($pendingHistoryIds)) {
            $ids = array_filter(array_map('intval', explode(',', $pendingHistoryIds)));
            foreach ($ids as $phId) {
                $updated = DB::table('tour_booking_history')
                    ->where('id', $phId)
                    ->where('is_processed', 0)
                    ->update([
                        'is_processed' => -1,
                        // Pax was never charged — nothing was kept, so zero out refund_keep_us.
                        'details'      => DB::raw("JSON_SET(COALESCE(details, '{}'), '$.refund_keep_us', 0)"),
                        'updated_at'   => Carbon::now(),
                    ]);
                if ($updated) {
                    $linkItem = TourBookingPaymentLinkItem::where('reference_id', $phId)
                        ->where('type', 'change_people')
                        ->first();
                    if ($linkItem) {
                        $paymentLinkId                  = $linkItem->payment_link_id;
                        $linkItem->status               = 'Cancelled';
                        $linkItem->updated_by           = Auth::id();
                        $linkItem->updated_by_user_type = function_exists('userType') ? userType() : 'Admin';
                        $linkItem->save();
                        self::recalculateRemainingPaymentLink($paymentLinkId);
                    }
                }
            }
        }
    }

    /**
     * Admin "Cancel" action from the booking page's Payment Link Details modal.
     *
     * Previously this only flipped the link to Cancelled. For a remaining_payment link that left
     * every add-on it was collecting for still attached to the booking — its field rows were
     * written when the link was sent, and an add-on's pending amount is never added to
     * tour_bookings_master.total — so the booking kept showing the add-on while reading "Paid in
     * Full" with Make Payment disabled: an add-on nothing could ever collect.
     *
     * Now the add-ons the link itself ADDED (never paid) are removed along with it — see
     * removePendingAddonsOfCancelledLink(). Every other item type (change_people, custom_charge,
     * discount, switch_package) is left exactly as before.
     *
     * The link's open Stripe Checkout Session is expired first, so the customer can't still
     * complete a payment against a link that no longer exists. If Stripe reports that session as
     * already PAID, the cancel is refused instead — the payment just hasn't been recorded yet.
     */
    public static function cancelPaymentLinkByAdmin(TourBookingPaymentLink $link): void
    {
        if ($link->status === 'Paid') {
            throw new Exception('This payment link has already been paid and can no longer be cancelled.');
        }

        self::expireCheckoutSessionForCancelledLink($link);

        DB::beginTransaction();
        try {
            if ($link->section === 'remaining_payment') {
                self::removePendingAddonsOfCancelledLink($link);
            }

            $link->status = 'Cancelled';
            $link->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Expire the link's Stripe Checkout Session (if one was ever opened) before it is cancelled.
     * Best-effort like createPaymentLink(): an already-expired session is fine to ignore. The one
     * case that must NOT be ignored is a session the customer already paid — the cancel is
     * refused so that payment isn't orphaned against a cancelled link.
     */
    private static function expireCheckoutSessionForCancelledLink(TourBookingPaymentLink $link): void
    {
        if (empty($link->stripe_session_id)) {
            return;
        }

        try {
            app(PaymentGatewayInterface::class)->expireCheckoutSession($link->stripe_session_id);
            return;
        } catch (\Exception) {
            // Expired or completed — find out which below.
        }

        try {
            $session = Session::retrieve($link->stripe_session_id);
        } catch (\Exception) {
            return;
        }

        if (($session->payment_status ?? null) === 'paid') {
            throw new Exception('The customer has already paid this payment link. Please refresh the page before making any changes.');
        }
    }

    /**
     * Remove the add-ons a remaining_payment link was collecting for, as part of cancelling it.
     *
     * Only rows the link's own request ADDED are removed:
     *  - the item's details.added / details.pax[].added entries (Booking Add-ons editor and
     *    Passenger Add-ons "Pay with Link"), resolved through details.field_to_db;
     *  - its to_charge lines without an old_db_id (brand-new add-ons — this also covers the
     *    Change People add-on items, which carry no 'added' list).
     *
     * Deliberately NOT reusing cancelPendingAddonLinkItem(), which deletes EVERY field_to_db row:
     * field_to_db also maps rows the same request only UPDATED in place (e.g. an already-paid
     * add-on upgraded via the link), and deleting those would drop an add-on the customer already
     * paid for. It also recalculates (new token + customer email) after every single item.
     *
     * As a final guard, a row is only removed when it has never had any addon transaction at all
     * (paid, open or refunded) — a pending link add-on never has one until the link is paid.
     * Conditional child rows of a removed add-on go with it.
     */
    private static function removePendingAddonsOfCancelledLink(TourBookingPaymentLink $link): void
    {
        $addonItems = TourBookingPaymentLinkItem::where('payment_link_id', $link->id)
            ->where('type', 'addon')
            ->where('status', 'Unpaid')
            ->get();

        if ($addonItems->isEmpty()) {
            return;
        }

        $groupBookingIds = DB::table('tour_bookings_master')
            ->where('group_id', $link->order_id)
            ->pluck('id')
            ->all();

        foreach ($addonItems as $item) {
            $details = is_array($item->details)
                ? $item->details
                : (json_decode($item->details ?? '[]', true) ?? []);
            $fieldToDb = $details['field_to_db'] ?? [];
            $financial = $details['financial'] ?? [];

            // Rows this request only edited — never removed, even if a key happens to collide.
            $protectedIds = [];
            foreach (array_merge(
                $details['updated'] ?? [],
                collect($details['pax'] ?? [])->flatMap(fn ($pax) => $pax['updated'] ?? [])->all()
            ) as $updatedEntry) {
                if (!empty($updatedEntry['db_id'])) {
                    $protectedIds[] = (int) $updatedEntry['db_id'];
                }
            }
            foreach (array_merge($financial['to_charge'] ?? [], $financial['to_refund'] ?? []) as $line) {
                if (!empty($line['old_db_id'])) {
                    $protectedIds[] = (int) $line['old_db_id'];
                }
            }

            $candidateIds = [];
            foreach ($details['added'] ?? [] as $addedEntry) {
                $key = (string) ($addedEntry['field_id'] ?? '');
                if (isset($fieldToDb[$key])) {
                    $candidateIds[] = (int) $fieldToDb[$key];
                }
            }
            foreach ($details['pax'] ?? [] as $pax) {
                $paxIndex = $pax['pax_index'] ?? null;
                foreach ($pax['added'] ?? [] as $addedEntry) {
                    $key = ($paxIndex !== null && $paxIndex !== '')
                        ? $addedEntry['field_id'] . '_' . $paxIndex
                        : (string) ($addedEntry['field_id'] ?? '');
                    if (isset($fieldToDb[$key])) {
                        $candidateIds[] = (int) $fieldToDb[$key];
                    }
                }
            }
            foreach ($financial['to_charge'] ?? [] as $line) {
                if (empty($line['old_db_id'])) {
                    $candidateIds[] = self::resolvePendingAddonLineFieldId($line, $fieldToDb);
                }
            }

            $candidateIds = array_values(array_diff(
                array_unique(array_filter($candidateIds)),
                $protectedIds
            ));

            $removeIds = self::neverTransactedAddonFieldIds($candidateIds, $groupBookingIds);

            // Conditional children of a removed add-on (parent_id) go with it.
            $parentIds = $removeIds;
            while (!empty($parentIds)) {
                $childIds = TourBookingAddonField::whereIn('parent_id', $parentIds)
                    ->whereNotIn('id', array_merge($removeIds, $protectedIds))
                    ->pluck('id')
                    ->all();
                $parentIds = self::neverTransactedAddonFieldIds($childIds, $groupBookingIds);
                $removeIds = array_merge($removeIds, $parentIds);
            }

            if (!empty($removeIds)) {
                TourBookingAddonField::whereIn('id', $removeIds)->delete();
            }

            // Addon link amounts are never added to tour_bookings_master.total / booking_total
            // (see cancelPendingAddonLinkItem()), so there are no master totals to reverse.
            $item->status               = 'Cancelled';
            $item->updated_by           = Auth::id();
            $item->updated_by_user_type = function_exists('userType') ? userType() : 'Admin';
            $item->save();
        }
    }

    /**
     * The subset of $fieldIds (limited to $bookingIds) that are still active and have never had
     * any tour_booking_addon_transactions row, including soft-deleted ones.
     *
     * @param  int[] $fieldIds
     * @param  int[] $bookingIds
     * @return int[]
     */
    private static function neverTransactedAddonFieldIds(array $fieldIds, array $bookingIds): array
    {
        if (empty($fieldIds) || empty($bookingIds)) {
            return [];
        }

        return TourBookingAddonField::whereIn('id', $fieldIds)
            ->whereIn('tour_booking_id', $bookingIds)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('tour_booking_addon_transactions as tbat')
                    ->whereColumn('tbat.tour_booking_addon_id', 'tour_booking_addon_fields.id');
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Cancel a pending addon payment-link item and recalculate the parent remaining_payment link.
     * Also soft-deletes the tour_booking_addon_fields rows that were written when the link was
     * created — they are unpaid and must not persist once the link is cancelled.
     * If this was the only item on the link, the link itself is cancelled.
     */
    public static function cancelPendingAddonLinkItem(int $itemId): void
    {
        $linkItem = TourBookingPaymentLinkItem::find($itemId);
        if (!$linkItem || $linkItem->type !== 'addon') {
            return;
        }

        // Delete the addon field rows written to tour_booking_addon_fields at link-creation time.
        // details['field_to_db'] is a "{field_id}_{pax_index}" => tour_booking_addon_fields.id map.
        $details = is_array($linkItem->details)
            ? $linkItem->details
            : (json_decode($linkItem->details ?? '[]', true) ?? []);
        $fieldToDb = $details['field_to_db'] ?? [];
        if (!empty($fieldToDb)) {
            $addonFieldIds = array_values(array_filter(array_map('intval', $fieldToDb)));
            if (!empty($addonFieldIds)) {
                TourBookingAddonField::whereIn('id', $addonFieldIds)->delete();
            }
        }

        // Addon payment link amounts are never added to tour_bookings_master.total /
        // booking_total — the booking page computes pricedAddons['addons_payment_total']
        // dynamically from active addon fields. Subtracting here would corrupt the master
        // totals, so we skip the master update entirely and just remove the link item.
        $paymentLinkId                  = $linkItem->payment_link_id;
        $linkItem->status               = 'Cancelled';
        $linkItem->updated_by           = Auth::id();
        $linkItem->updated_by_user_type = function_exists('userType') ? userType() : 'Admin';
        $linkItem->save();
        self::recalculateRemainingPaymentLink($paymentLinkId);
    }

    /**
     * Cancel pending addon payment-link items ORPHANED by deleting a pax from this booking —
     * i.e. items whose own charge no longer covers any addon field that still exists.
     *
     * Previously this cancelled EVERY type='addon' item on the booking's Unpaid remaining_payment
     * link unconditionally, with no scoping to the pax actually being deleted. A booking can carry
     * several independent pending addon items (one per pax whose add-ons were sent via link) — e.g.
     * pax A's add-on is still genuinely pending while pax B is the one being deleted here — and the
     * blanket cancel silently wiped pax A's pending item (and, once nothing remained, the whole
     * link/its "Pending Payment Link" banner) even though pax A was never touched.
     *
     * Scoping matches the same old_db_id/field_to_db pattern already used by
     * TourBookingAddonRepository::cancelPendingAddonLinkItemsForDeletedFields() (the addon-field-
     * edit equivalent of this fix) and TourBookingRepository::movePendingAddonPaymentLinkItems()
     * (the split-booking equivalent): an item is only cancelled once NONE of the addon fields its
     * own charge/refund lines reference still exist — removePaxAddons()/deletePaxAddons() already
     * (soft-)deleted the deleted pax's own tour_booking_addon_fields rows by the time this runs, so
     * "none of its fields still exist" reliably means "this item belonged to the deleted pax", while
     * an untouched pax's fields are still there and the item is left alone.
     *
     * A single item can bundle MULTIPLE pax's fields together (e.g. two children added via the
     * same payment-link resend, each with their own priced add-on, land on one combined item) —
     * deleting just one of those pax previously left the WHOLE item (and its full amount)
     * untouched, since "any field still exists" was true. The item's own to_charge/to_refund
     * lines are now individually re-checked and any entry whose field is gone gets dropped, with
     * amount/platform_fees/net_amount recomputed from whatever entries remain — so removing one of
     * two bundled pax correctly halves the item instead of leaving it at its full original amount.
     */
    public static function cancelPendingAddonLinkItemsForBooking(int $bookingId): void
    {
        $groupId = DB::table('tour_bookings_master')->where('id', $bookingId)->value('group_id');
        if (!$groupId) {
            return;
        }

        $link = TourBookingPaymentLink::where('order_id', $groupId)
            ->where('status', 'Unpaid')
            ->where('section', 'remaining_payment')
            ->first();

        if (!$link) {
            return;
        }

        $addonItems = TourBookingPaymentLinkItem::where('payment_link_id', $link->id)
            ->where('type', 'addon')
            ->where('status', 'Unpaid')
            ->get();

        foreach ($addonItems as $item) {
            $details = is_array($item->details) ? $item->details : (json_decode($item->details ?? '[]', true) ?? []);
            $financial = $details['financial'] ?? [];
            $fieldToDb = $details['field_to_db'] ?? [];

            $resolveFieldId = fn (array $lineItem): ?int => self::resolvePendingAddonLineFieldId($lineItem, $fieldToDb);

            $toCharge = $financial['to_charge'] ?? [];
            $toRefund = $financial['to_refund'] ?? [];

            $fieldIds = [];
            foreach (array_merge($toCharge, $toRefund) as $lineItem) {
                $fid = $resolveFieldId($lineItem);
                if ($fid !== null) {
                    $fieldIds[] = $fid;
                }
            }
            $fieldIds = array_unique(array_filter($fieldIds));

            // No field ids could be resolved at all (unexpected shape) — leave it alone rather
            // than risk cancelling an unrelated pax's item on a guess.
            if (empty($fieldIds)) {
                continue;
            }

            $existingFieldIds = TourBookingAddonField::whereIn('id', $fieldIds)->pluck('id')
                ->map(fn ($id) => (int) $id)->all();

            if (count($existingFieldIds) === count($fieldIds)) {
                // Every referenced field still exists — nothing here belongs to a deleted pax.
                continue;
            }

            if (empty($existingFieldIds)) {
                // Nothing referenced by this item still exists — the whole item belonged to the
                // deleted pax.
                self::cancelPendingAddonLinkItem($item->id);
                continue;
            }

            // Partial: some (not all) referenced fields are gone — keep only the entries whose
            // field still exists, and recompute this item's totals from just those.
            $keptCharge = array_values(array_filter($toCharge, fn ($li) => in_array($resolveFieldId($li), $existingFieldIds, true)));
            $keptRefund = array_values(array_filter($toRefund, fn ($li) => in_array($resolveFieldId($li), $existingFieldIds, true)));

            $chargeGross = array_sum(array_map(
                fn ($li) => (float) ($li['charge_amount'] ?? 0) + (float) ($li['field_fee'] ?? 0) + (float) ($li['option_fee'] ?? 0),
                $keptCharge
            ));
            $chargeNet = array_sum(array_map(fn ($li) => (float) ($li['charge_amount'] ?? 0), $keptCharge));
            $chargeFees = array_sum(array_map(
                fn ($li) => (float) ($li['field_fee'] ?? 0) + (float) ($li['option_fee'] ?? 0),
                $keptCharge
            ));
            $refundGross = array_sum(array_map(fn ($li) => (float) ($li['refund_amount'] ?? 0), $keptRefund));

            $newAmount = round($chargeGross - $refundGross, 2);
            if ($newAmount <= 0.001) {
                // Nothing left with any real value once the deleted pax's entries are dropped
                // (e.g. the surviving entries all net to $0) — same outcome as no field
                // surviving at all.
                self::cancelPendingAddonLinkItem($item->id);
                continue;
            }

            $keptFieldToDbKeys = [];
            foreach (array_merge($keptCharge, $keptRefund) as $li) {
                $lookupKey = self::resolvePendingAddonLineLookupKey($li);
                if (isset($fieldToDb[$lookupKey])) {
                    $keptFieldToDbKeys[$lookupKey] = $fieldToDb[$lookupKey];
                }
            }

            $details['financial']['to_charge'] = $keptCharge;
            $details['financial']['to_refund'] = $keptRefund;
            $details['financial']['net_amount'] = round($chargeNet - $refundGross, 2);
            $details['field_to_db'] = $keptFieldToDbKeys;
            $details['platform_fees'] = round($chargeFees, 2);

            $item->amount                  = $newAmount;
            $item->details                 = $details;
            $item->updated_by              = Auth::id();
            $item->updated_by_user_type    = function_exists('userType') ? userType() : 'Admin';
            $item->save();
            self::recalculateRemainingPaymentLink($link->id);
        }
    }

    /**
     * Move whichever share of a pending (Unpaid) `type='addon'` remaining_payment link item
     * belongs to the given moved add-on fields onto the NEW booking's own remaining_payment link —
     * used by both the plain (all-paid-pax) and mixed (paid+unpaid-pax) split flows, right after
     * AddonBookingService::splitBookingPaxAddons() has already re-pointed the
     * tour_booking_addon_fields rows themselves onto the new booking.
     *
     * Without this, the fields move but the money still owed for them stays attached to the OLD
     * booking's link — and worse, createAddonTransactionsFromFields() later sees these fields on
     * the new booking with no transaction row at all and wrongly settles them as already paid (see
     * its own pendingAddonFieldIdsForBooking() exclusion, which this method's NEW item is what that
     * exclusion detects going forward).
     *
     * A single item can bundle MULTIPLE pax's fields together (e.g. all 4 pax's add-ons sent via
     * one payment-link resend land on one combined item) — splitting off only SOME of those pax
     * must partially reduce the item (proportional fee share included), not cancel or move it in
     * full, mirroring cancelPendingAddonLinkItemsForBooking()'s own per-field partial handling.
     *
     * @param int   $oldBookingId
     * @param int   $newBookingId
     * @param array $movedAddonFieldIds tour_booking_addon_fields.id list that were just moved
     */
    public static function movePendingAddonPaymentLinkItems(int $oldBookingId, int $newBookingId, array $movedAddonFieldIds): void
    {
        if (empty($movedAddonFieldIds)) {
            return;
        }

        $oldBooking = TourBooking::find($oldBookingId);
        $newBooking = TourBooking::find($newBookingId);
        if (!$oldBooking || !$newBooking) {
            return;
        }

        $oldLink = TourBookingPaymentLink::where('order_id', $oldBooking->group_id)
            ->where('section', 'remaining_payment')
            ->where('status', 'Unpaid')
            ->first();
        if (!$oldLink) {
            return;
        }

        $addonItems = TourBookingPaymentLinkItem::where('payment_link_id', $oldLink->id)
            ->where('type', 'addon')
            ->where('status', 'Unpaid')
            ->get();

        $userType = function_exists('userType') ? userType() : 'Admin';

        foreach ($addonItems as $item) {
            $details   = is_array($item->details) ? $item->details : (json_decode($item->details ?? '[]', true) ?? []);
            $financial = $details['financial'] ?? [];
            $fieldToDb = $details['field_to_db'] ?? [];

            $resolveFieldId = fn (array $lineItem): ?int => self::resolvePendingAddonLineFieldId($lineItem, $fieldToDb);

            $partition = function (array $lines) use ($resolveFieldId, $movedAddonFieldIds): array {
                $moved = $staying = [];
                foreach ($lines as $line) {
                    $fid = $resolveFieldId($line);
                    if ($fid !== null && in_array($fid, $movedAddonFieldIds, true)) {
                        $moved[] = $line;
                    } else {
                        $staying[] = $line;
                    }
                }
                return [$moved, $staying];
            };

            [$movedToCharge, $stayingToCharge] = $partition($financial['to_charge'] ?? []);
            [$movedToRefund, $stayingToRefund] = $partition($financial['to_refund'] ?? []);

            if (empty($movedToCharge) && empty($movedToRefund)) {
                continue;
            }

            $sum = fn (array $lines, string $key) => round(array_sum(array_map(fn ($l) => (float) ($l[$key] ?? 0), $lines)), 2);

            $isFullMove = empty($stayingToCharge) && empty($stayingToRefund);

            $existingNet = (float) ($financial['net_amount'] ?? 0);
            $existingFee = (float) ($financial['fee_amount'] ?? ($details['platform_fees'] ?? 0));

            if ($isFullMove) {
                // Every line belongs to the moved fields — move the item's own recorded total
                // as-is, regardless of whether its `financial` breakdown carries per-line amounts
                // (older items created before that breakdown was added only ever match this
                // all-or-nothing shape, never the partial one below).
                $movedTotal = (float) $item->amount;
            } else {
                $movedNet      = round($sum($movedToCharge, 'charge_amount') - $sum($movedToRefund, 'refund_amount'), 2);
                $movedFeeShare = ($existingNet > 0 && $existingFee > 0) ? round($existingFee * ($movedNet / $existingNet), 2) : 0.0;
                $movedTotal    = round($movedNet + $movedFeeShare, 2);
            }

            if ($movedTotal <= 0) {
                continue;
            }

            $slotInfo = TourPackageService::getTourSlotInfo($newBooking->tour_slot_id, $userType);
            if (!$slotInfo) {
                continue;
            }

            if ($isFullMove) {
                $item->status               = 'Cancelled';
                $item->updated_by           = Auth::id();
                $item->updated_by_user_type = $userType;
                $item->save();
            } else {
                $stayingNet = round($existingNet - $movedNet, 2);
                $stayingFee = round($existingFee - $movedFeeShare, 2);

                $financial['to_charge']       = array_values($stayingToCharge);
                $financial['to_refund']       = array_values($stayingToRefund);
                $financial['net_amount']      = $stayingNet;
                $financial['fee_amount']      = $stayingFee;
                $financial['total_charge']    = $sum($stayingToCharge, 'charge_amount');
                $financial['total_refund']    = $sum($stayingToRefund, 'refund_amount');
                $financial['total_to_charge'] = round($stayingNet + $stayingFee, 2);

                $details['financial']     = $financial;
                $details['platform_fees'] = $stayingFee;

                $item->amount               = round($stayingNet + $stayingFee, 2);
                $item->details              = json_encode($details, JSON_NUMERIC_CHECK);
                $item->updated_by           = Auth::id();
                $item->updated_by_user_type = $userType;
                $item->save();
            }

            PaymentLinkService::recalculateRemainingPaymentLink($oldLink->id);

            if ($isFullMove) {
                // $financial/$details are still the ORIGINAL, untouched values here — the whole
                // item (100% of its lines) is moving, so the new item is just a verbatim copy.
                $newDetails = $details;
            } else {
                $newFinancial = array_merge($financial, [
                    'to_charge'       => array_values($movedToCharge),
                    'to_refund'       => array_values($movedToRefund),
                    'net_amount'      => $movedNet,
                    'fee_amount'      => $movedFeeShare,
                    'total_charge'    => $sum($movedToCharge, 'charge_amount'),
                    'total_refund'    => $sum($movedToRefund, 'refund_amount'),
                    'total_to_charge' => $movedTotal,
                ]);

                $newDetails                  = $details; // $details here already holds the STAYING slice (saved to the old item above)
                $newDetails['financial']     = $newFinancial;
                $newDetails['platform_fees'] = $movedFeeShare;
            }
            $newDetails['booking_id'] = $newBookingId;

            $slotDateTime = Carbon::parse($slotInfo->date . ' ' . $slotInfo->time_from);

            PaymentLinkService::upsertRemainingPaymentLink(
                $newBooking->group_id,
                [
                    'type'         => 'addon',
                    'reference_id' => null,
                    'amount'       => $movedTotal,
                    'reason'       => 'Payment for add-ons on your booking',
                    'details'      => $newDetails,
                ],
                $newBooking,
                $slotDateTime,
                $slotInfo
            );
        }
    }

    /**
     * Resolve which of a booking's tour_booking_addon_fields.id are currently covered by an active
     * (Unpaid) `type='addon'` remaining_payment link item — i.e. genuinely still pending payment,
     * not yet actually collected.
     *
     * Used by AddonBookingService::createAddonTransactionsFromFields() to exclude these fields from
     * its "no transaction row yet" bucket: without this, a field that's only pending because it's
     * sitting on an unpaid payment link (as opposed to a genuine fresh $0-cost selection) gets
     * silently settled as 'paid' the next time ANYTHING calls that method for the booking (e.g. a
     * split's post-move finalization, or an unrelated Make Payment) — before the customer has
     * actually paid the link at all.
     *
     * @return int[]
     */
    public static function pendingAddonFieldIdsForBooking(int $bookingId): array
    {
        $groupId = DB::table('tour_bookings_master')->where('id', $bookingId)->value('group_id');
        if (!$groupId) {
            return [];
        }

        $link = TourBookingPaymentLink::where('order_id', $groupId)
            ->where('section', 'remaining_payment')
            ->where('status', 'Unpaid')
            ->first();
        if (!$link) {
            return [];
        }

        $addonItems = TourBookingPaymentLinkItem::where('payment_link_id', $link->id)
            ->where('type', 'addon')
            ->where('status', 'Unpaid')
            ->get();

        $fieldIds = [];
        foreach ($addonItems as $item) {
            $details   = is_array($item->details) ? $item->details : (json_decode($item->details ?? '[]', true) ?? []);
            $financial = $details['financial'] ?? [];
            $fieldToDb = $details['field_to_db'] ?? [];

            foreach (array_merge($financial['to_charge'] ?? [], $financial['to_refund'] ?? []) as $lineItem) {
                $fid = self::resolvePendingAddonLineFieldId($lineItem, $fieldToDb);
                if ($fid !== null) {
                    $fieldIds[] = $fid;
                }
            }
        }

        return array_values(array_unique(array_filter($fieldIds)));
    }

    /**
     * Resolve which tour_booking_addon_fields row a pending 'addon' link item's to_charge /
     * to_refund line belongs to: its own old_db_id first, then the item's field_to_db map.
     *
     * field_to_db is keyed "{field_id}_{pax_index}" for pax add-ons, but by the plain field_id
     * for booking-level (non-pax) add-ons (see TourBookingAddonRepository::upsertBookingAddonFields()),
     * whose lines carry no pax_index at all — the old inline "{field_id}_{pax_index ?? ''}" lookup
     * built "12_" for those and never matched anything.
     */
    public static function resolvePendingAddonLineFieldId(array $lineItem, array $fieldToDb): ?int
    {
        if (!empty($lineItem['old_db_id'])) {
            return (int) $lineItem['old_db_id'];
        }

        $lookupKey = self::resolvePendingAddonLineLookupKey($lineItem);
        return isset($fieldToDb[$lookupKey]) ? (int) $fieldToDb[$lookupKey] : null;
    }

    /**
     * The field_to_db lookup key a given to_charge/to_refund line maps to: "{field_id}_{pax_index}"
     * for a pax-structured line, or the bare field_id for a booking-level one. Single source of
     * truth for this rule — resolvePendingAddonLineFieldId() above and
     * cancelPendingAddonLinkItemsForBooking()'s $keptFieldToDbKeys reconstruction both key off it,
     * so a future change to how pax_index is matched only needs to happen here once.
     */
    private static function resolvePendingAddonLineLookupKey(array $lineItem): string
    {
        $fieldId  = $lineItem['field_id'] ?? '';
        $paxIndex = $lineItem['pax_index'] ?? null;

        return ($paxIndex !== null && $paxIndex !== '')
            ? $fieldId . '_' . $paxIndex
            : (string) $fieldId;
    }

    /**
     * Keep a pending (Unpaid) 'addon' remaining_payment link item in step with an EDIT of an add-on
     * field it covers — e.g. a T-Shirt sent via "Pay with Link" as X-Large ($25), then changed to
     * Large ($22) before the customer paid. Deletions were already handled (the item is cancelled
     * or shrunk), but an edit left the item — and so the link's amount, token and email — at the
     * OLD selection's price, and paying it would have settled the field at that old price too.
     *
     * For each to_charge line of an unpaid addon item whose field is in $updatedFieldIds:
     *  - field in $supersededFieldIds (this same request is charging its FULL new price itself,
     *    e.g. an upgrade paid by cash or sent as a new link item) → the line is dropped, so the
     *    customer isn't billed for both the old and the new selection;
     *  - otherwise → the line is repriced from the field's CURRENT (already-updated) selection,
     *    using the same pricing rule as the add-ons editor (_computePriceBreakdown()).
     * The item's net_amount/platform_fees/amount are then recomputed the same way
     * TourBookingAddonRepository::createAddonPaymentLink() originally computed them, and the link
     * is recalculated — which regenerates its token (the old URL stops working) and emails the
     * customer the new amount. An item left with nothing owed is cancelled WITHOUT deleting its
     * add-on field rows (unlike cancelPendingAddonLinkItem()) — those fields still exist, only
     * their pending charge is gone.
     *
     * @param int   $bookingId
     * @param int[] $updatedFieldIds     tour_booking_addon_fields ids edited in this request
     * @param int[] $supersededFieldIds  subset whose full new price this request charges itself
     * @param bool  $recalculate         false when the caller recalculates/emails the link itself
     */
    public static function syncPendingAddonLinkItemsForUpdatedFields(
        int $bookingId,
        array $updatedFieldIds,
        array $supersededFieldIds = [],
        bool $recalculate = true,
        ?float $feePct = null
    ): void {
        $updatedFieldIds    = array_values(array_unique(array_map('intval', array_filter($updatedFieldIds))));
        $supersededFieldIds = array_values(array_unique(array_map('intval', array_filter($supersededFieldIds))));
        if (empty($updatedFieldIds)) {
            return;
        }

        $groupId = DB::table('tour_bookings_master')->where('id', $bookingId)->value('group_id');
        if (!$groupId) {
            return;
        }

        $link = TourBookingPaymentLink::where('order_id', $groupId)
            ->where('section', 'remaining_payment')
            ->where('status', 'Unpaid')
            ->first();
        if (!$link) {
            return;
        }

        $addonItems = TourBookingPaymentLinkItem::where('payment_link_id', $link->id)
            ->where('type', 'addon')
            ->where('status', 'Unpaid')
            ->orderBy('id')
            ->get();
        if ($addonItems->isEmpty()) {
            return;
        }

        // One field can end up on several pending lines (e.g. its original line plus a later
        // upgrade-difference line). Repricing sets a line to the field's WHOLE current price
        // (minus what was actually paid), so only the first line per field may carry it — every
        // further line for the same field is dropped, or the field is billed once per line
        // (booking #41960: two $17.57 lines for one $16 lunch).
        $repricedFieldIds = [];

        // A caller that already has this booking's BookingTransact row (e.g. createAddonPaymentLink(),
        // which needs it again right after this call returns) can pass the percentage in directly
        // to avoid an identical, avoidable second query for the same row in the same request.
        $feePct   = $feePct ?? (float) (BookingTransact::where('tour_booking_id', $bookingId)->where('deleted_by', 0)->value('nat_fees_percentage') ?? 0);
        $userType = function_exists('userType') ? userType() : 'Admin';
        $changed  = false;

        // Every field this method can ever reprice is bounded by $updatedFieldIds (already
        // deduplicated above) — batch-fetch all of them plus their underlying AddonField/
        // AddonFieldOption defs once, instead of re-querying per to_charge line per item (up to
        // 3 queries per line: TourBookingAddonField::find(), then 2 more inside
        // currentAddonFieldPriceBreakdown()).
        $updatedFieldsById = TourBookingAddonField::whereIn('id', $updatedFieldIds)->get()->keyBy('id');
        $addonFieldDefsById = \App\Models\AddonField::whereIn('id', $updatedFieldsById->pluck('field_id')->filter()->unique())
            ->get()->keyBy('id');
        $addonFieldOptionsById = \App\Models\AddonFieldOption::whereIn('id', $updatedFieldsById->pluck('option_id')->filter()->unique())
            ->get()->keyBy('id');

        foreach ($addonItems as $item) {
            $details = is_array($item->details) ? $item->details : (json_decode($item->details ?? '[]', true) ?? []);
            if ((int) ($details['booking_id'] ?? $bookingId) !== $bookingId) {
                continue;
            }

            $financial = $details['financial'] ?? [];
            $fieldToDb = $details['field_to_db'] ?? [];
            $toCharge  = $financial['to_charge'] ?? [];
            $toRefund  = $financial['to_refund'] ?? [];

            $oldChargeTotal = array_sum(array_map(fn ($li) => (float) ($li['charge_amount'] ?? 0), $toCharge));
            $itemChanged    = false;
            $keptCharge     = [];

            foreach ($toCharge as $line) {
                $dbId = self::resolvePendingAddonLineFieldId($line, $fieldToDb);
                if ($dbId === null || !in_array($dbId, $updatedFieldIds, true)) {
                    $keptCharge[] = $line;
                    continue;
                }

                if (in_array($dbId, $supersededFieldIds, true)) {
                    // Full new price is charged by the caller — drop this line, and its
                    // field_to_db entry so a later cancelPendingAddonLinkItem() on this item
                    // can never delete the (now separately charged) field row.
                    foreach ($fieldToDb as $key => $mappedId) {
                        if ((int) $mappedId === $dbId) {
                            unset($fieldToDb[$key]);
                        }
                    }
                    $itemChanged = true;
                    continue;
                }

                $field = $updatedFieldsById->get($dbId);
                if (!$field) {
                    $keptCharge[] = $line;
                    continue;
                }

                if (isset($repricedFieldIds[$dbId])) {
                    $itemChanged = true; // already repriced on an earlier line — drop this duplicate
                    continue;
                }
                $repricedFieldIds[$dbId] = true;

                $price = self::currentAddonFieldPriceBreakdown($field, $addonFieldDefsById, $addonFieldOptionsById);
                $label = $field->field_label ?: ($line['label'] ?? 'Add-on');
                if (!empty($field->option_label)) {
                    $label .= ' (' . $field->option_label . ')';
                }

                $repriced = array_merge($line, [
                    'label'                 => $label,
                    'option_id'             => $field->option_id,
                    'unit'                  => max(1, (int) $field->unit),
                    'new_price'             => $price['total'],
                    'charge_amount'         => round($price['total'] - (float) ($line['old_paid'] ?? 0), 2),
                    'field_price'           => $price['field_price'],
                    'option_price'          => $price['option_price'],
                    'field_additional_fee'  => $price['field_additional_fee'],
                    'option_additional_fee' => $price['option_additional_fee'],
                ]);

                if ($repriced != $line) {
                    $itemChanged = true;
                }
                if ($repriced['charge_amount'] > 0.001) {
                    $keptCharge[] = $repriced;
                } else {
                    // Now a $0 selection — nothing left to collect for this field.
                    $itemChanged = true;
                }

                // Keep the stored diff (used by AddonItemHandler::finalize() only to re-create a
                // missing field row) pointing at the new selection too.
                $patchDiff = function (array $entries) use ($field) {
                    foreach ($entries as $i => $entry) {
                        if ((int) ($entry['field_id'] ?? 0) === (int) $field->field_id
                            && (!isset($entry['db_id']) || (int) $entry['db_id'] === (int) $field->id)) {
                            $entries[$i]['option_id']    = $field->option_id;
                            $entries[$i]['option_label'] = $field->option_label;
                            $entries[$i]['value']        = $field->value;
                            $entries[$i]['unit']         = $field->unit;
                        }
                    }
                    return $entries;
                };
                if ($field->pax_index === null) {
                    foreach (['added', 'updated'] as $bucket) {
                        if (!empty($details[$bucket]) && is_array($details[$bucket])) {
                            $details[$bucket] = $patchDiff($details[$bucket]);
                        }
                    }
                } else {
                    foreach ($details['pax'] ?? [] as $p => $paxGroup) {
                        if (($paxGroup['pax_index'] ?? null) != $field->pax_index) {
                            continue;
                        }
                        foreach (['added', 'updated'] as $bucket) {
                            if (!empty($paxGroup[$bucket]) && is_array($paxGroup[$bucket])) {
                                $details['pax'][$p][$bucket] = $patchDiff($paxGroup[$bucket]);
                            }
                        }
                    }
                }
            }

            if (!$itemChanged) {
                continue;
            }
            $changed = true;

            $totals = self::recomputePendingAddonItemTotals($financial, $keptCharge, $feePct);

            if ($totals === null) {
                $item->status               = 'Cancelled';
                $item->updated_by           = Auth::id();
                $item->updated_by_user_type = $userType;
                $item->save();
                continue;
            }

            $newNet         = $totals['net'];
            $newChargeTotal = $totals['charge_total'];
            $platformFees   = $totals['fees'];

            $details['financial']['to_charge']    = $keptCharge;
            $details['financial']['net_amount']   = $newNet;
            $details['financial']['total_charge'] = round($newChargeTotal, 2);
            $details['field_to_db']               = $fieldToDb;
            $details['platform_fees']             = $platformFees;

            $item->amount               = round($newNet + $platformFees, 2);
            $item->details              = $details;
            $item->updated_by           = Auth::id();
            $item->updated_by_user_type = $userType;
            $item->save();
        }

        if ($changed && $recalculate) {
            self::recalculateRemainingPaymentLink($link->id);
        }
    }

    /**
     * Recompute a pending 'addon' link item's totals once some of its to_charge lines change or
     * are dropped — shared by syncPendingAddonLinkItemsForUpdatedFields() (which saves it) and
     * pendingAddonLinkDropAmounts()/pendingAddonLinkLineShares() (which only preview it), so the
     * three can never disagree on what dropping a line is worth.
     *
     * Net is delta-based so any refund offset baked into the original net (a remove+add edit sent
     * as one link) is preserved as-is. Fees follow createAddonPaymentLink()'s rule: with a refund
     * offset, on the net; otherwise per line on what's still OWED (charge_amount), so a
     * partially-paid line (old_paid > 0) has its field/option prices scaled down first.
     *
     * @return array{net: float, charge_total: float, fees: float, amount: float}|null  null when
     *         nothing is left owed (the item would be cancelled)
     */
    private static function recomputePendingAddonItemTotals(array $financial, array $keptCharge, float $feePct): ?array
    {
        $toCharge = $financial['to_charge'] ?? [];
        $toRefund = $financial['to_refund'] ?? [];

        $oldChargeTotal = array_sum(array_map(fn ($li) => (float) ($li['charge_amount'] ?? 0), $toCharge));
        $newChargeTotal = array_sum(array_map(fn ($li) => (float) ($li['charge_amount'] ?? 0), $keptCharge));
        $oldNet = isset($financial['net_amount'])
            ? (float) $financial['net_amount']
            : $oldChargeTotal - array_sum(array_map(fn ($li) => (float) ($li['credit_amount'] ?? $li['refund_amount'] ?? 0), $toRefund));
        $newNet = round($oldNet + ($newChargeTotal - $oldChargeTotal), 2);

        if (empty($keptCharge) || $newNet <= 0.001) {
            return null;
        }

        if (!empty($toRefund)) {
            $platformFees = round($newNet * $feePct / 100, 2);
        } else {
            $platformFees = 0.0;
            foreach ($keptCharge as $line) {
                $chargeAmount   = (float) ($line['charge_amount'] ?? 0);
                $rawFieldPrice  = isset($line['field_price']) ? (float) $line['field_price'] : $chargeAmount;
                $rawOptionPrice = isset($line['option_price']) ? (float) $line['option_price'] : 0.0;
                $rawTotal       = $rawFieldPrice + $rawOptionPrice;
                if ($rawTotal > 0 && abs($rawTotal - $chargeAmount) > 0.001) {
                    $scale       = $chargeAmount / $rawTotal;
                    $fieldPrice  = round($rawFieldPrice * $scale, 2);
                    $optionPrice = round($chargeAmount - $fieldPrice, 2);
                } else {
                    $fieldPrice  = $rawFieldPrice;
                    $optionPrice = $rawOptionPrice;
                }
                $platformFees += round($fieldPrice * $feePct / 100, 2) + round($optionPrice * $feePct / 100, 2);
            }
            $platformFees = round($platformFees, 2);
        }

        return [
            'net'          => $newNet,
            'charge_total' => round($newChargeTotal, 2),
            'fees'         => $platformFees,
            'amount'       => round($newNet + $platformFees, 2),
        ];
    }

    /**
     * The booking's Unpaid 'addon' remaining_payment link items, each with its decoded details —
     * the same set syncPendingAddonLinkItemsForUpdatedFields() walks.
     *
     * @return array<int, array{item: TourBookingPaymentLinkItem, details: array}>
     */
    private static function unpaidAddonLinkItemsForBooking(int $bookingId): array
    {
        $groupId = DB::table('tour_bookings_master')->where('id', $bookingId)->value('group_id');
        if (!$groupId) {
            return [];
        }

        $link = TourBookingPaymentLink::where('order_id', $groupId)
            ->where('section', 'remaining_payment')
            ->where('status', 'Unpaid')
            ->first();
        if (!$link) {
            return [];
        }

        $out = [];
        $items = TourBookingPaymentLinkItem::where('payment_link_id', $link->id)
            ->where('type', 'addon')
            ->where('status', 'Unpaid')
            ->get();
        foreach ($items as $item) {
            $details = is_array($item->details) ? $item->details : (json_decode($item->details ?? '[]', true) ?? []);
            if ((int) ($details['booking_id'] ?? $bookingId) !== $bookingId) {
                continue;
            }
            $out[] = ['item' => $item, 'details' => $details];
        }

        return $out;
    }

    /**
     * How much the booking's pending link would shrink if the given fields' pending lines were
     * dropped — exactly what syncPendingAddonLinkItemsForUpdatedFields() does to a field this same
     * request charges in full itself (an upgrade of a still-pending add-on, which is charged its
     * full new price with old_paid = 0). Used to build a Card charge of "this edit + whatever the
     * link will still be owed AFTER the upgrade replaces its old line", instead of the link's
     * current total, which still contains the old selection and would charge it twice.
     *
     * @param int[] $fieldIds
     * @return array{amount: float, fees: float}  amount includes fees
     */
    public static function pendingAddonLinkDropAmounts(int $bookingId, array $fieldIds, ?array $items = null, ?float $feePct = null, array $repriceSelections = []): array
    {
        $fieldIds = array_values(array_unique(array_map('intval', array_filter($fieldIds))));
        $drop     = ['amount' => 0.0, 'fees' => 0.0];
        if (empty($fieldIds) && empty($repriceSelections)) {
            return $drop;
        }

        $feePct = $feePct ?? (float) (BookingTransact::where('tour_booking_id', $bookingId)->where('deleted_by', 0)->value('nat_fees_percentage') ?? 0);

        // First line per field only, same as syncPendingAddonLinkItemsForUpdatedFields().
        $repricedFieldIds = [];

        foreach ($items ?? self::unpaidAddonLinkItemsForBooking($bookingId) as ['item' => $item, 'details' => $details]) {
            $financial = $details['financial'] ?? [];
            $fieldToDb = $details['field_to_db'] ?? [];
            $toCharge  = $financial['to_charge'] ?? [];

            $keptCharge = [];
            $changed    = false;
            foreach ($toCharge as $line) {
                $dbId = self::resolvePendingAddonLineFieldId($line, $fieldToDb);
                if ($dbId !== null && in_array($dbId, $fieldIds, true)) {
                    $changed = true;
                    continue;
                }
                if ($dbId !== null && isset($repriceSelections[$dbId])) {
                    if (isset($repricedFieldIds[$dbId])) {
                        $changed = true;
                        continue;
                    }
                    $repricedFieldIds[$dbId] = true;
                    $repriced = self::previewRepricedAddonLine($line, $dbId, $repriceSelections[$dbId]);
                    if ($repriced === null) {
                        $keptCharge[] = $line;
                        continue;
                    }
                    if ($repriced != $line) {
                        $changed = true;
                    }
                    if ($repriced['charge_amount'] > 0.001) {
                        $keptCharge[] = $repriced;
                    }
                    continue;
                }
                $keptCharge[] = $line;
            }
            if (!$changed) {
                continue;
            }

            $totals = self::recomputePendingAddonItemTotals($financial, array_values($keptCharge), $feePct);
            $drop['amount'] += (float) $item->amount - ($totals['amount'] ?? 0.0);
            $drop['fees']   += (float) ($details['platform_fees'] ?? 0) - ($totals['fees'] ?? 0.0);
        }

        return ['amount' => round($drop['amount'], 2), 'fees' => round($drop['fees'], 2)];
    }

    /**
     * A pending link line as syncPendingAddonLinkItemsForUpdatedFields() WILL reprice it once the
     * field's new selection is saved — computed beforehand from an in-memory copy of the field, so
     * the Card PaymentIntent (created before the edit is committed) can already reflect it.
     *
     * @param array $selection ['option_id' => ?int, 'unit' => ?int, 'field_id' => ?int]
     */
    private static function previewRepricedAddonLine(array $line, int $dbId, array $selection): ?array
    {
        $field = TourBookingAddonField::find($dbId);
        if (!$field) {
            return null;
        }
        $field->option_id = $selection['option_id'] ?? null;
        if (isset($selection['unit'])) {
            $field->unit = $selection['unit'];
        }

        $defs    = \App\Models\AddonField::whereIn('id', array_filter([$field->field_id]))->get()->keyBy('id');
        $options = \App\Models\AddonFieldOption::whereIn('id', array_filter([$field->option_id]))->get()->keyBy('id');
        $price   = self::currentAddonFieldPriceBreakdown($field, $defs, $options);

        return array_merge($line, [
            'option_id'             => $field->option_id,
            'unit'                  => max(1, (int) $field->unit),
            'new_price'             => $price['total'],
            'charge_amount'         => round($price['total'] - (float) ($line['old_paid'] ?? 0), 2),
            'field_price'           => $price['field_price'],
            'option_price'          => $price['option_price'],
            'field_additional_fee'  => $price['field_additional_fee'],
            'option_additional_fee' => $price['option_additional_fee'],
        ]);
    }

    /**
     * Per add-on field (tour_booking_addon_fields.id): what dropping just that field's pending
     * link line would take off the link, split into net and fee — pendingAddonLinkDropAmounts() for
     * one field at a time. Exposed to the add-ons editors so their "Payment Required" preview can
     * show an upgrade of a still-pending add-on as the real difference owed, and a total that
     * matches what the Card charge / regenerated link will actually be.
     *
     * @return array<int, array{net: float, fee: float}>
     */
    public static function pendingAddonLinkLineShares(int $bookingId): array
    {
        // Rendered into every booking page (tour-booking-info.blade.php) — a malformed link item
        // must never break the page; with no shares the add-ons preview behaves exactly as before.
        try {
            return self::computePendingAddonLinkLineShares($bookingId);
        } catch (\Throwable $e) {
            Log::error('pendingAddonLinkLineShares failed for booking ' . $bookingId . ': ' . $e->getMessage());
            return [];
        }
    }

    private static function computePendingAddonLinkLineShares(int $bookingId): array
    {
        $shares = [];
        $items  = self::unpaidAddonLinkItemsForBooking($bookingId);
        if (empty($items)) {
            return self::computeLumpSumAddonLinkShares($bookingId);
        }
        $feePct = (float) (BookingTransact::where('tour_booking_id', $bookingId)->where('deleted_by', 0)->value('nat_fees_percentage') ?? 0);

        foreach ($items as ['details' => $details]) {
            $fieldToDb = $details['field_to_db'] ?? [];
            foreach ($details['financial']['to_charge'] ?? [] as $line) {
                $dbId = self::resolvePendingAddonLineFieldId($line, $fieldToDb);
                if ($dbId !== null) {
                    $shares[$dbId] = true;
                }
            }
        }

        foreach (array_keys($shares) as $dbId) {
            $drop = self::pendingAddonLinkDropAmounts($bookingId, [$dbId], $items, $feePct);
            $shares[$dbId] = [
                'net' => round($drop['amount'] - $drop['fees'], 2),
                'fee' => $drop['fees'],
            ];
        }

        return $shares;
    }

    /**
     * Pay Later booking whose pending balance is the LUMP-SUM link (section=NULL — no itemized
     * addon lines): every priced add-on is folded into that link's single amount, price + fee,
     * by BookingService::fetchPayLaterAddonsWithPrice(), and an edit only moves the link by the
     * (price + fee) difference (TourBookingAddonRepository::syncPayLaterAddonsPaymentLink()).
     * Expose each field's share the same way so the "Payment Required" preview shows an upgrade
     * as the real difference (e.g. $16 → $18 = +$2) instead of the full new price on top.
     * 'lump_sum' tells the preview the link is fee-inclusive even when the payment method
     * (Pay Later / Billable) itself carries no fee.
     *
     * @return array<int, array{net: float, fee: float, lump_sum: bool}>
     */
    private static function computeLumpSumAddonLinkShares(int $bookingId): array
    {
        $master = DB::table('tour_bookings_master')->where('id', $bookingId)->first(['group_id']);
        if (!$master || !$master->group_id) {
            return [];
        }
        $hasPayment = DB::table('transactions')->where('tour_booking_id', $bookingId)->where('status', 'Completed')->exists();
        if ($hasPayment) {
            return [];
        }
        $hasLumpSumLink = TourBookingPaymentLink::where('order_id', $master->group_id)
            ->where('status', 'Unpaid')
            ->where(function ($q) {
                $q->whereNull('section')->orWhere('section', '!=', 'remaining_payment');
            })
            ->exists();
        if (!$hasLumpSumLink) {
            return [];
        }

        $feePct = (float) (BookingTransact::where('tour_booking_id', $bookingId)->where('deleted_by', 0)->value('nat_fees_percentage') ?? 0);

        // Same query/pricing as fetchPayLaterAddonsWithPrice()'s addon-field section, keyed by row id.
        $rows = DB::table('tour_booking_addon_fields as tbaf')
            ->join('addon_fields as af', 'af.id', '=', 'tbaf.field_id')
            ->leftJoin('addon_field_options as afo', 'afo.id', '=', 'tbaf.option_id')
            ->where('tbaf.tour_booking_id', $bookingId)
            ->whereNull('tbaf.deleted_at')
            ->select([
                'tbaf.id',
                'af.additional_fee as field_additional_fee',
                'af.price as field_price',
                'afo.additional_fee as option_additional_fee',
                'afo.price as option_price',
                'tbaf.unit_type',
                'tbaf.value',
            ])
            ->get();

        $shares = [];
        foreach ($rows as $row) {
            $fieldPrice  = ($row->field_additional_fee  && $row->field_price  > 0) ? (float) $row->field_price  : 0.0;
            $optionPrice = ($row->option_additional_fee && $row->option_price > 0) ? (float) $row->option_price : 0.0;
            if ($fieldPrice <= 0 && $optionPrice <= 0) {
                continue;
            }
            $qty = ($row->unit_type === 'price_per_unit') ? max(1, (int) $row->value) : 1;
            $shares[(int) $row->id] = [
                'net'      => round(($fieldPrice + $optionPrice) * $qty, 2),
                'fee'      => round(round($fieldPrice * $feePct / 100, 2) * $qty + round($optionPrice * $feePct / 100, 2) * $qty, 2),
                'lump_sum' => true,
            ];
        }

        return $shares;
    }

    /**
     * Price of an add-on field's CURRENT selection — mirrors the add-ons editor's
     * _computePriceBreakdown() (tour-booking-addons.js): field/option prices count only when
     * flagged additional_fee with a positive price, multiplied by quantity for price_per_unit.
     *
     * @return array{field_price: float, option_price: float, total: float, field_additional_fee: bool, option_additional_fee: bool}
     */
    private static function currentAddonFieldPriceBreakdown(
        TourBookingAddonField $field,
        \Illuminate\Support\Collection $defsById,
        \Illuminate\Support\Collection $optionsById
    ): array {
        $def    = $defsById->get($field->field_id);
        $option = $field->option_id ? $optionsById->get($field->option_id) : null;

        $fieldFlag  = $def && $def->additional_fee && (float) $def->price > 0;
        $optionFlag = $option && $option->additional_fee && (float) $option->price > 0;

        $fieldPrice  = $fieldFlag ? (float) $def->price : 0.0;
        $optionPrice = $optionFlag ? (float) $option->price : 0.0;

        $unitType = $def->unit_type ?? $field->unit_type;
        if ($unitType === 'price_per_unit') {
            // Floored at 1, matching TourBookingAddonRepository::resolveConfiguredAddonPrice()'s
            // identical rule — without this floor, a field explicitly saved with unit=0 priced as
            // $0 via this reprice path but as unit=1 via that one, so whether a "0 units" edit is
            // billed silently depended on which of the two call paths ran.
            $qty         = max(1, (int) $field->unit);
            $fieldPrice  = round($fieldPrice * $qty, 2);
            $optionPrice = round($optionPrice * $qty, 2);
        }

        return [
            'field_price'           => round($fieldPrice, 2),
            'option_price'          => round($optionPrice, 2),
            'total'                 => round($fieldPrice + $optionPrice, 2),
            'field_additional_fee'  => (bool) $fieldFlag,
            'option_additional_fee' => (bool) $optionFlag,
        ];
    }

    /**
     * Recalculate the total on a remaining_payment link after any item is added or removed,
     * then sync the link record and send an updated email to the customer.
     *
     * New total = sum of remaining TourBookingPaymentLinkItems
     *           + sum of Open custom charges in the booking group that are NOT already
     *             linked as items (created via editBooking / Pay Later paths).
     *
     * Outcomes:
     *  - New total > 0 and differs from current amount → update amount, regenerate token,
     *    set status back to Unpaid (handles Expired), send payment-link email.
     *  - New total = 0 → cancel the link (no money owed).
     *  - New total unchanged → no-op.
     *
     * This method is public so it can be called from any service or repository that mutates
     * payment-link items (e.g. cancelPendingChangePeopleItems, future refund flows, etc.).
     */
    public static function recalculateRemainingPaymentLink(int|string $paymentLinkId): void
    {
        $link = TourBookingPaymentLink::find($paymentLinkId);
        if (!$link || $link->section !== 'remaining_payment') {
            return;
        }

        // Already in a terminal state — nothing to recalculate
        if (in_array($link->status, ['Paid', 'Cancelled'])) {
            return;
        }

        $groupBookingIds = DB::table('tour_bookings_master')
            ->where('group_id', $link->order_id)
            ->pluck('id');

        // Find custom charges that are NOT yet linked on any active (Unpaid/Expired)
        // remaining_payment link item. These were created via editBooking / Pay Later paths
        // and need a TourBookingPaymentLinkItem row so the link's items table is complete.
        $activeLinkedChargeIds = DB::table('tour_booking_payment_link_items as tbpli')
            ->join('tour_booking_payment_links as tbpl', 'tbpl.id', '=', 'tbpli.payment_link_id')
            ->where('tbpli.type', 'custom_charge')
            ->whereNotNull('tbpli.reference_id')
            ->whereIn('tbpli.status', ['Unpaid', 'Expired'])
            ->whereIn('tbpl.status', ['Unpaid', 'Expired'])
            ->where('tbpl.section', 'remaining_payment')
            ->pluck('tbpli.reference_id')
            ->toArray();

        $unlinkedOpenCharges = DB::table('tour_booking_custom_charges')
            ->whereIn('tour_booking_id', $groupBookingIds)
            ->where('status', 'Open')
            ->when(!empty($activeLinkedChargeIds), fn($q) => $q->whereNotIn('id', $activeLinkedChargeIds))
            ->get();

        // Create items for every unlinked open charge on this link
        $chargeCreatorType = function_exists('userType') ? userType() : 'Admin';
        foreach ($unlinkedOpenCharges as $charge) {
            TourBookingPaymentLinkItem::create([
                'payment_link_id'      => $link->id,
                'type'                 => 'custom_charge',
                'reference_id'         => $charge->id,
                'amount'               => round((float) $charge->amount + (float) $charge->fees, 2),
                'status'               => 'Unpaid',
                // Store platform_fees in details so createPaymentLink's $stripeFees
                // computation can include it in application_fee_amount without a
                // separate DB re-read that may race with fees being set later.
                'details'              => json_encode(['platform_fees' => (float) $charge->fees], JSON_NUMERIC_CHECK),
                'created_by'           => Auth::id() ?? $charge->operation_by ?? null,
                'created_by_user_type' => $chargeCreatorType,
                'updated_by'           => Auth::id() ?? $charge->operation_by ?? null,
                'updated_by_user_type' => $chargeCreatorType,
            ]);
        }

        // Recalculate total from Unpaid items only (Cancelled/Expired items are excluded).
        $newTotal = round(
            (float) TourBookingPaymentLinkItem::where('payment_link_id', $paymentLinkId)->where('status', 'Unpaid')->sum('amount'),
            2
        );

        // Cancel if nothing remains — also cascade status to any remaining Unpaid items.
        if ($newTotal <= 0) {
            TourBookingPaymentLinkItem::where('payment_link_id', $paymentLinkId)
                ->where('status', 'Unpaid')
                ->update(['status' => 'Cancelled', 'updated_at' => Carbon::now()]);
            $link->status     = 'Cancelled';
            $link->updated_at = Carbon::now();
            $link->save();
            return;
        }

        $amountChanged = round((float) $link->amount, 2) !== $newTotal;
        $itemsCreated  = $unlinkedOpenCharges->isNotEmpty();

        // Nothing to update — amount is already correct and no new items were added
        if (!$amountChanged && !$itemsCreated) {
            return;
        }

        // Update link: correct amount, fresh token (invalidates old URL), restore to Unpaid
        $link->amount          = $newTotal;
        $link->status          = 'Unpaid';
        $link->token           = self::generateUniqueToken();
        $link->override_status = 'Yes';
        $link->updated_at      = Carbon::now();
        $link->save();

        // Send updated payment-link email so customer has the new URL and correct amount.
        // Deferred via DB::afterCommit() so the email is never dispatched while a caller's
        // transaction is still open — keeping the transaction (and any row locks it holds on
        // tour_bookings_master) as short as possible. When there is no active transaction the
        // callback fires immediately, so behaviour outside a transaction is unchanged.
        $tourBooking = TourBooking::where('group_id', $link->order_id)
            ->orderBy('id')
            ->first();

        if (!$tourBooking) {
            return;
        }

        $capturedBooking       = $tourBooking;
        $capturedLink          = $link;
        $capturedPaymentLinkId = $paymentLinkId;

        DB::afterCommit(function () use ($capturedBooking, $capturedLink, $capturedPaymentLinkId) {
            try {
                $userType = function_exists('userType') ? userType() : 'Admin';
                $info = TourPackageService::getTourSlotInfo($capturedBooking->tour_slot_id, $userType);
                if ($info) {
                    EmailService::sendPaymentLinkEmail($info, $capturedBooking, $capturedLink);
                }
            } catch (\Exception $e) {
                Log::error('recalculateRemainingPaymentLink: email send failed', [
                    'payment_link_id' => $capturedPaymentLinkId,
                    'error'           => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * Resend the payment link email for a remaining_payment link after its amount changes
     * (e.g. permit fee update for unpaid pax).  Fetches slot/operator info via
     * TourPackageService so the caller does not need the inline DB query.
     */
    public static function resendRemainingPaymentLinkEmail(object $tourBooking, TourBookingPaymentLink $remainingLink): void
    {
        try {
            $userType = function_exists('userType') ? userType() : 'Admin';
            $info = TourPackageService::getTourSlotInfo($tourBooking->tour_slot_id, $userType);
            if ($info) {
                EmailService::sendPaymentLinkEmail($info, $tourBooking, $remainingLink);
            }
        } catch (\Exception $e) {
            Log::error('resendRemainingPaymentLinkEmail: email send failed', [
                'booking_id'      => $tourBooking->id,
                'payment_link_id' => $remainingLink->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update the remaining_payment link amount and write a history entry when an unpaid
     * pax's permit fee is changed (no Stripe refund — money has not moved yet).
     */
    public static function processUnpaidPermitFeeDelta(
        $request,
        float $unpaidPermitFeeDelta,
        float $natFeePct,
        object $tourbooking,
        string $createdByWithName,
        array $unpaidPaxCountByRateFor
    ): void {
        $freshBooking  = TourBooking::find($request->bookingID);
        $remainingLink = TourBookingPaymentLink::where('order_id', $freshBooking->group_id)
            ->where('status', 'Unpaid')
            ->where('section', 'remaining_payment')
            ->first();

        if ($remainingLink) {
            $linkItem = TourBookingPaymentLinkItem::where('payment_link_id', $remainingLink->id)
                ->where('status', 'Unpaid')
                ->where('type', 'change_people')
                ->where(function ($q) use ($request) {
                    $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(details, '$.tour_booking_id')) = ?", [(string)$request->bookingID])
                      ->orWhereRaw("JSON_EXTRACT(details, '$.tour_booking_id') IS NULL");
                })
                ->latest('id')
                ->first();

            if ($linkItem) {
                $itemDetails = is_array($linkItem->details)
                    ? $linkItem->details
                    : (json_decode($linkItem->details ?? '[]', true) ?? []);

                $oldTicket   = (float)($itemDetails['ticket_cost'] ?? 0);
                $oldPlatform = (float)($itemDetails['platform_fees'] ?? 0);
                $newTicket   = round($oldTicket + $unpaidPermitFeeDelta, 2);
                $newPlatform = round($newTicket * $natFeePct / 100, 2);
                $newAmount   = round($newTicket + $newPlatform, 2);

                $itemDetails['ticket_cost']   = $newTicket;
                $itemDetails['platform_fees'] = $newPlatform;
                $linkItem->amount  = $newAmount;
                $linkItem->details = json_encode($itemDetails);
                $linkItem->save();

                $remainingLink->amount = $newAmount;
                $remainingLink->save();

                $masterDelta = round($unpaidPermitFeeDelta + ($newPlatform - $oldPlatform), 2);
                TourBooking::where('id', $request->bookingID)->update([
                    'total'         => DB::raw("total + {$masterDelta}"),
                    'booking_total' => DB::raw("booking_total + {$masterDelta}"),
                ]);

                static::resendRemainingPaymentLinkEmail($freshBooking, $remainingLink);
            }
        }

        $unpaidTextforDeleted = '';
        $uIdx = 0;
        foreach ($unpaidPaxCountByRateFor as $uRateFor => $uCount) {
            $unpaidTextforDeleted .= ($uIdx === 0 ? '' : ', ') . $uRateFor . ' person ' . $uCount;
            $uIdx++;
        }

        $unpaidHistoryId = HistoryService::storeRefundDataInHistory(
            $request->bookingID,
            $freshBooking->tourists + $freshBooking->infants,
            $createdByWithName,
            null,
            'Permitfees Refund',
            null,
            abs($unpaidPermitFeeDelta),
            null,
            [],
            [],
            '',
            null,
            null,
            0,
            $unpaidTextforDeleted
        );

        PermitFeeRefundBookingService::run($tourbooking, null, $unpaidHistoryId, $request->all(), '');
    }

    /**
     * Compute the ticket-cost amount to add back to $total in the mixed Change People path.
     * processCalculationsChangePeople() subtracted deleted-pax cost from $total, but unpaid pax
     * were never charged to Stripe — so their cost must be restored before the charge/refund
     * sign decision is made.
     *
     * @param  array<int,int> $unpaidCountByRateGroupId   [tour_package_rate_id => unpaid_count]
     * @param  array<int,int> $rateGroupWiseDeletedArray  [tour_package_rate_id => deleted_count]
     * @param  int|string     $bookingId
     * @return float  total correction to add back to $total (>= 0)
     */
    public static function computeMixedPathUnpaidCostCorrection(
        array $unpaidCountByRateGroupId,
        array $rateGroupWiseDeletedArray,
        int|string $bookingId
    ): float {
        if (empty($unpaidCountByRateGroupId)) {
            return 0.0;
        }

        $correction = 0.0;
        foreach ($rateGroupWiseDeletedArray as $rgId => $deletedCount) {
            $unpaidCount = min((int)$deletedCount, $unpaidCountByRateGroupId[$rgId] ?? 0);
            if ($unpaidCount <= 0) continue;

            $btRow = BookingTransact::where('tour_booking_id', $bookingId)
                ->where('tour_package_rate_id', $rgId)
                ->where('deleted_by', 0)
                ->first();
            if (!$btRow) continue;

            $rate = $btRow->rate + $btRow->additional_charge + $btRow->permit_fee + $btRow->partner_fees_amount;
            $per  = $rate * ($btRow->additional_tax_percentage + $btRow->processing_charge_percentage) / 100;
            $correction += round(($rate + $per) * $unpaidCount, 2);
        }

        return round($correction, 2);
    }

    /**
     * Compute the ticket-cost and fee amounts to subtract from Stripe refund-validation totals
     * for pax that carry a pending payment link (i.e. never actually charged to Stripe).
     *
     * @param  array<int,int> $rateCountArray           [tour_package_rate_id => deleted_count]
     * @param  int            $bookingId
     * @param  array<int,int> $unpaidCountByTransactId  [transact_id => unpaid_count]
     * @return array{amount: float, fee: float}
     */
    public static function computeUnpaidPaxValidationDeduction(
        array $rateCountArray,
        int $bookingId,
        array $unpaidCountByTransactId
    ): array {
        if (empty($unpaidCountByTransactId)) {
            return ['amount' => 0.0, 'fee' => 0.0];
        }

        $totalAmount = 0.0;
        $totalFee    = 0.0;

        foreach ($rateCountArray as $packageRateId => $deletedCount) {
            $transact = BookingTransact::where('tour_package_rate_id', $packageRateId)
                ->where('tour_booking_id', $bookingId)
                ->where('deleted_by', 0)
                ->first();

            if (!$transact) continue;

            $unpaidCount = min((int)$deletedCount, $unpaidCountByTransactId[$transact->id] ?? 0);
            if ($unpaidCount <= 0) continue;

            $rate          = $transact->rate + $transact->additional_charge + $transact->permit_fee + $transact->partner_fees_amount;
            $per           = $rate * ($transact->additional_tax_percentage + $transact->processing_charge_percentage) / 100;
            $unpaidSubtotal = round(($rate + $per) * $unpaidCount, 2);
            $unpaidFee      = roundout($unpaidSubtotal * $transact->nat_fees_percentage / 100);

            $totalAmount += $unpaidSubtotal;
            $totalFee    += $unpaidFee;
        }

        return [
            'amount' => round($totalAmount, 2),
            'fee'    => round($totalFee, 2),
        ];
    }

    /**
     * Handle the Payment Link section of paymentNewBooking() for both V1 and V3 repositories.
     *
     * Branch 1 — remaining_link_id present: resend the existing link email (Payment Link method)
     *             or finalize via Stripe (Card method).
     * Branch 2 — payment_method == 'Payment Link': create a new link and send the email.
     *
     * @param  mixed   $request
     * @param  object  $info
     * @param  array   $tourBooking
     * @param  string  $paymentIntentId
     * @param  string  $paymentMethod
     * @param  float   $linkPlatformFee  Pre-calculated NAT platform fee (caller-specific logic); 0 for Branch 1.
     * @return \Illuminate\Http\JsonResponse|null  Non-null means the caller should return immediately.
     */
    public static function handleMakePaymentSection(
        $request,
        object $info,
        array $tourBooking,
        string $paymentIntentId,
        string $paymentMethod,
        float $linkPlatformFee = 0.0,
        float $natFeesPercentage = 0.0
    ) {
        // Branch 1: pending remaining_payment link — resend email or finalize via card
        if (!empty($request->remaining_link_id)) {
            $remainingLink = TourBookingPaymentLink::where('id', $request->remaining_link_id)
                ->where('status', 'Unpaid')
                ->where('section', 'remaining_payment')
                ->first();

            if (!$remainingLink) {
                DB::commit();
                return response()->json([
                    'errors' => 'This pending payment has already been processed. Please refresh the page.',
                    'paid'   => 1,
                ]);
            }

            if ($remainingLink) {
                $freshBooking = TourBooking::find($request->bookingID);

                if ($paymentMethod === 'Payment Link') {
                    EmailService::sendPaymentLinkEmail($info, $freshBooking, $remainingLink);
                    DB::commit();
                    return response()->json([
                        'tourBookingId'    => $request->bookingID,
                        'totalBookedSeats' => $info->seats,
                        'splitCount'       => 'Please select option',
                    ]);
                }

                if ($paymentMethod === 'Card' && !empty($paymentIntentId)) {
                    $piWithCharges = app(PaymentGatewayInterface::class)->retrievePayment($paymentIntentId, ['expand' => ['charges']]);
                    self::finalizeRemainingPaymentLink($remainingLink, $piWithCharges, $request->bookingID);

                    // Guard against duplicate transaction records: the Stripe webhook
                    // (StripePaymentIntentSucceededForMakePayment) may have already promoted
                    // the Initialized row to Completed before this reconcile path runs.
                    // Only write the transaction when no Completed record exists for this PI.
                    $transactionAlreadyRecorded = DB::table('transactions')
                        ->where('tour_booking_id', $request->bookingID)
                        ->where('transaction_number', $paymentIntentId)
                        ->where('status', 'Completed')
                        ->exists();

                    if (!$transactionAlreadyRecorded) {
                        TransactionService::storePaymentInTransaction(
                            $request->bookingID,
                            $remainingLink->amount,
                            0,
                            'Card',
                            0,
                            $paymentIntentId,
                            $piWithCharges->charges->data[0]->application_fee ?? null,
                            mode: PaymentModeInference::fromStripeObject($piWithCharges)
                        );
                    }

                    DB::commit();
                    return response()->json([
                        'tourBookingId'    => $request->bookingID,
                        'totalBookedSeats' => $info->seats,
                        'splitCount'       => 'Please select option',
                    ]);
                }
            }
        }

        // Branch 2: new Payment Link — create link record and send email
        if ($request->payment_method == 'Payment Link') {
            if (!hasSetting('PAYMENT_LINK')) {
                throw new Exception('Payment link feature is not enabled.');
            }
            if ($tourBooking[0]->total == 0) {
                throw new Exception('No payment required for this booking.');
            }

            // Ticket base + NAT platform fee (caller-computed, V1/V3 differ here)
            $linkTotal = $tourBooking[0]->total + $linkPlatformFee;

            // Unpaid pay-later add-ons (amount + fees already included in addons_total)
            $addonsAmount = BookingService::fetchPayLaterAddonsWithPrice($request->bookingID, $natFeesPercentage);
            $linkTotal += $addonsAmount['addons_total'] ?? 0;

            // Unpaid custom charges (amount + fees already included in pay_later_custom_charge_total)
            $customChargePrice = CustomChargeBookingService::customChargePrice($request->bookingID);
            if (!empty($customChargePrice['custom_charge_final_total']) && $customChargePrice['custom_charge_final_total'] > 0) {
                $linkTotal += $customChargePrice['pay_later_custom_charge_total'];
            }

            // Unpaid tips (total + fees)
            $tipAmounts = TipService::getTipAmounts($request->bookingID);
            if (!empty($tipAmounts['pay_later_tip_total']) && $tipAmounts['pay_later_tip_total'] > 0) {
                $linkTotal += $tipAmounts['pay_later_tip_total'] + ($tipAmounts['pay_later_tip_fees'] ?? 0);
            }

            $linkTotal    = round($linkTotal, 2);
            $freshBooking = TourBooking::find($request->bookingID);
            $slotDateTime = Carbon::parse($tourBooking[0]->Date . ' ' . $tourBooking[0]->StartTime);

            $paymentLinkJson = json_encode([
                'amount'            => $linkTotal,
                'reason_of_payment' => 'Make Payment',
                'customer_email'    => $tourBooking[0]->Email,
                'customer_phone_no' => $tourBooking[0]->ContactNumber,
            ]);

            // Pass overrideExpiry=true so the link works even when the tour is today/past
            $createdLink = self::storeTourBookingPaymentLink(
                $freshBooking->group_id,
                $paymentLinkJson,
                $slotDateTime,
                null,
                true
            );

            EmailService::sendPaymentLinkEmail($info, $freshBooking, $createdLink);

            return response()->json([
                'tourBookingId'    => $request->bookingID,
                'totalBookedSeats' => $info->seats,
                'splitCount'       => 'Please select option',
            ]);
        }

        return null;
    }
}