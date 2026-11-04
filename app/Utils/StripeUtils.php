<?php

namespace App\Utils;

use App\Constants\Constants;
use App\Models\Customer;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Config;
use App\Models\SaveCreditCard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\Booking\HistoryService;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Services\Payments\PaymentModeResolver;

class StripeUtils
{
    /**
     * Stripe rejects a PaymentIntent / Charge description longer than this
     * with an InvalidRequestException ("must be at most 1000 characters").
     */
    public const MAX_DESCRIPTION_LENGTH = 1000;

    /**
     * Trim a description to Stripe's maximum length.
     *
     * Combo descriptions list every booking in the cart, so a large cart
     * (7 packages is enough) goes over the limit and the Stripe update fails.
     *
     * @param string|null $description
     * @return string|null
     */
    public static function limitDescription(?string $description): ?string
    {
        if ($description === null || mb_strlen($description) <= self::MAX_DESCRIPTION_LENGTH) {
            return $description;
        }

        $suffix = "\n...";

        return mb_substr($description, 0, self::MAX_DESCRIPTION_LENGTH - mb_strlen($suffix)) . $suffix;
    }

    /**
     * Create Stripe Payment Intent for new booking.
     */
    public static function createStripePaymentIntentNewBooking($info, $stripeTotal, $stripeFee, $description, $data)
    {
        try {
            $statementDescriptor = $info->statement_descriptor ?: self::buildDescriptorFallback($info->tour_operator, $info->package_name);
            $mode = app(PaymentModeResolver::class)->resolveForSingleDate($info->date);
            $paymentIntent = self::createStripeIntent($statementDescriptor, $stripeTotal, $stripeFee, $description, $data, $info->stripe_destination_id, mode: $mode, isPartnerBooking: ($info->is_partner_package ?? 0) == 1);

            return [
                'clientSecret' => $paymentIntent['clientSecret'],
                'savedCards' => $paymentIntent['savedCards'],
            ];
        } catch (Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }
    /**
     * Process a Stripe payment intent for POS.
     *
     * @param array $tourBookings
     * @param object $request
     * @param float $stripeTotal
     * @param float $stripeFees
     * @return array
     */
    public static function createStripePaymentIntentPos($stripeTotal, $stripeFees, $description, $metadata, $destination, $statementDescriptor, array $tourDates = []): array
    {
        $mode = app(PaymentModeResolver::class)->resolve($tourDates);
        $paymentIntentParams = [
            'payment_method_types' => ['card'], // Only allows card payments (includes Apple & Google Pay)
            'statement_descriptor_suffix' => $statementDescriptor,
            'currency' => currencyFormation(true, false),
            'amount' => $stripeTotal * 100, // Convert to cents
            'description' => $description,
            'metadata' => [
                'bookings' => json_encode($metadata),
                'tenant_id' => tenant()->id,
            ],
            ...app(PaymentGatewayInterface::class)->buildConnectedPaymentData($destination, (int) round($stripeFees * 100), mode: $mode)
        ];

        $paymentIntent = app(PaymentGatewayInterface::class)->createPayment($paymentIntentParams);

        return [
            'clientSecret' => $paymentIntent->client_secret
        ];
    }
    /**
     * Process a Stripe payment intent.
     *
     * @param object $tourBooking
     * @param object $request
     * @param float $stripeTotal
     * @param float $stripeFees
     * @return array
     */
    public static function createStripePaymentIntentMakePayment(object $tourBooking, object $request, float $stripeTotal, float $stripeFees, $partnerFeesTotal = 0, $payoutAmount = 0): array
    {
        $data = self::buildMakePaymentIntentData($tourBooking, $request, $stripeTotal, $stripeFees, $partnerFeesTotal, $payoutAmount);
        $mode = app(PaymentModeResolver::class)->resolveForSingleDate($tourBooking->Date);

        $paymentIntent = self::createStripeIntent($data['statementDescriptor'], $stripeTotal, $stripeFees, $data['description'], $data['metadata'], $tourBooking->stripe_destination_id, $data['email'], mode: $mode, isPartnerBooking: $partnerFeesTotal > 0);

        return [
            'clientSecret'    => $paymentIntent['clientSecret'],
            'paymentIntentId' => $paymentIntent['paymentIntentId'],
            'savedCards'      => $paymentIntent['savedCards'],
        ];
    }

    /**
     * Update an existing (not-yet-terminal) make-payment PaymentIntent in place — used when a
     * booking's outstanding balance changed after the PaymentIntent was created but before it
     * was confirmed, so we refresh it instead of creating a redundant second one.
     */
    public static function updateStripePaymentIntentMakePayment(string $paymentIntentId, object $tourBooking, object $request, float $stripeTotal, float $stripeFees, $partnerFeesTotal = 0, $payoutAmount = 0)
    {
        $data = self::buildMakePaymentIntentData($tourBooking, $request, $stripeTotal, $stripeFees, $partnerFeesTotal, $payoutAmount);
        $mode = app(PaymentModeResolver::class)->resolveForSingleDate($tourBooking->Date);

        // Match createStripeIntent()'s metadata shape: these three are redundant with the
        // Stripe customer object and aren't included in the encoded metadata there either.
        $metadata = $data['metadata'];
        unset($metadata['email'], $metadata['customer_name'], $metadata['customer_phone']);

        // buildConnectedPaymentData() is shared with every PaymentIntent CREATE call site, where
        // on_behalf_of and transfer_data[destination] are valid — but this method updates an
        // EXISTING PaymentIntent, and Stripe's update endpoint rejects both ("Received unknown
        // parameter: on_behalf_of" / "transfer_data[destination]"; update only accepts
        // transfer_data[amount]). application_fee_amount and statement_descriptor(_suffix) remain
        // updatable pre-confirmation. The destination account is fixed at creation time regardless
        // — this refresh only needs to keep amount/fee/description/metadata in sync with the
        // booking's current total, not re-set who the charge is "for" or where it goes.
        $connectedData = app(PaymentGatewayInterface::class)->buildConnectedPaymentData($tourBooking->stripe_destination_id, (int) round($stripeFees * 100), mode: $mode);
        unset($connectedData['on_behalf_of'], $connectedData['transfer_data']);

        return app(PaymentGatewayInterface::class)->updatePayment($paymentIntentId, [
            'amount' => (int) round($stripeTotal * 100),
            'description' => $data['description'],
            'metadata' => [
                'bookings' => json_encode($metadata),
                'tenant_id' => tenant()->id,
            ],
            ...$connectedData,
        ]);
    }

    /**
     * Build the description/metadata/statement-descriptor shared by create and update of a
     * make-payment PaymentIntent.
     */
    private static function buildMakePaymentIntentData(object $tourBooking, object $request, float $stripeTotal, float $stripeFees, $partnerFeesTotal, $payoutAmount): array
    {
        // Create description for Stripe metadata
        $description = sprintf(
            "Booking Id: %s\nTour Package: %s\nTour Date: %s\nTour Time: %s\nTourists: %s\nEmail: %s",
            $request->bookingID,
            $tourBooking->package_name,
            $tourBooking->Date,
            $tourBooking->StartTime,
            $tourBooking->tourists + $tourBooking->infants,
            $tourBooking->Email
        );

        // Metadata for tracking payments
        $metadata = [
            'booking_id' => $request->bookingID,
            'tour_package' => $tourBooking->package_name,
            'tour_date' => $tourBooking->Date,
            'tour_time' => $tourBooking->StartTime,
            'tourists' => $tourBooking->tourists + $tourBooking->infants,
            'customer_name' => $tourBooking->Name,
            'customer_phone' => $tourBooking->ContactNumber,
            'email' => $tourBooking->Email,
            'amount' => $stripeTotal,
            'platform_fee' => $stripeFees ?? 0.00,
            'partner_fee' => $partnerFeesTotal ?? 0.00,
            'payout_amount' => $payoutAmount ?? 0.00,
            'split_payment' => $request->split_value ? 1 : 0,
        ];

        // Set the statement descriptor
        $statementDescriptor = $tourBooking->statement_descriptor ?: self::buildDescriptorFallback($tourBooking->tour_operator, $tourBooking->package_name);

        // Affiliate bookings must use the customer's own email as the Stripe customer identity
        $email = ($tourBooking->is_affiliate === 'Yes') ? $tourBooking->Email : '';

        return [
            'description' => $description,
            'metadata' => $metadata,
            'statementDescriptor' => $statementDescriptor,
            'email' => $email,
        ];
    }

    /**
     * Fetch the saved cards for a given Stripe customer ID, filtered to those
     * approved in our DB. Used when reusing an existing PaymentIntent on retry.
     */
    public static function getSavedCardsForCustomer(string $customerId): array
    {
        $paymentMethods = app(PaymentGatewayInterface::class)->listPaymentMethods([
            'customer' => $customerId,
            'type'     => 'card',
        ]);

        $dbSavedCards = SaveCreditCard::where('customer_id', $customerId)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('payment_method_id')
            ->flip()
            ->toArray();

        $savedCards = [];
        $seenCards  = [];

        foreach ($paymentMethods->data as $pm) {
            if (!isset($dbSavedCards[$pm->id])) {
                continue;
            }

            $fingerprintKey = strtolower($pm->card->brand) . '_' .
                              $pm->card->last4 . '_' .
                              $pm->card->exp_month . '_' .
                              $pm->card->exp_year;

            if (isset($seenCards[$fingerprintKey])) {
                continue;
            }

            $seenCards[$fingerprintKey] = true;

            $savedCards[] = [
                'id'        => $pm->id,
                'brand'     => ucfirst($pm->card->brand),
                'last4'     => $pm->card->last4,
                'exp_month' => $pm->card->exp_month,
                'exp_year'  => $pm->card->exp_year,
            ];
        }

        return $savedCards;
    }

    /**
     * Process a Stripe payment intent.
     *
     * @param array $tourBookings
     * @param object $request
     * @param float $stripeTotal
     * @param float $stripeFees
     * @return array
     */
    public static function createStripePaymentIntentMakeComboPayment($tourBookings, object $request, float $stripeTotal, float $stripeFees, $email = ''): array
    {
        $description = '';
        // Metadata for tracking payments
        foreach ($tourBookings as $tourBooking) {
            $metadata[] = [
                'booking_id' => $tourBooking['booking_id'],
                'email' => $tourBooking['email'],
                'customer_name' => $tourBooking['name'],
                'customer_phone' => $tourBooking['phone_number']

            ];
            $description .= sprintf(
                "Booking Id: %s",
                $tourBooking['booking_id']
            );
        }
        // Set the statement descriptor
        $statementDescriptor = $tourBookings[0]['statement_descriptor'] ?: self::buildDescriptorFallback($tourBookings[0]['tour_operator'] ?? '', $tourBookings[0]['package_name'] ?? '');
        $mode = app(PaymentModeResolver::class)->resolve(array_column($tourBookings, 'date'));
        // Any partner booking in the combo still needs transfer_group -- ThreeWayPaymentService
        // will read it back off this one shared charge for that booking's own guide/partner Transfer.
        $isPartnerBooking = in_array(1, array_column($tourBookings, 'is_partner_booking'));
        $paymentIntent = self::createStripeIntent($statementDescriptor, $stripeTotal, $stripeFees, $description, $metadata, $tourBookings[0]['stripe_destination_id'], $email, mode: $mode, isPartnerBooking: $isPartnerBooking);

        return [
            'clientSecret' => $paymentIntent['clientSecret'],
            'savedCards' => $paymentIntent['savedCards']
        ];
    }
    /**
     * Create a Stripe Payment Intent.
     *
     * @param array $amounts
     * @param array $data
     * @param object $info
     * @return array
     */
    public static function createStripePaymentIntentCart(array $amounts, array $data, object $info): array
    {
        $statementDescriptor = $info->statement_descriptor ?: self::buildDescriptorFallback($info->tour_operator, $info->package_name);

        // Check if customer exists or create a new one
        $customerEmail = $data['customer_email'];
        $customer = self::stripeCustomer($customerEmail, $data);

        // Retrieve saved payment methods
        $savedCards = [];
        $seenCards  = [];
        $pmParams = ['customer' => $customer->id, 'type' => 'card'];
        $paymentMethods = app(PaymentGatewayInterface::class)->listPaymentMethods($pmParams);

        // Get allowed payment_method_ids from DB
        $dbSavedCards = SaveCreditCard::where('customer_id', $customer->id)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('payment_method_id')
            ->flip()
            ->toArray();

        foreach ($paymentMethods->data as $pm) {

            // Only allow cards that exist in DB table
            if (!isset($dbSavedCards[$pm->id])) {
                continue;
            }

            // Create unique fingerprint
            $fingerprintKey = strtolower($pm->card->brand) . '_' .
                $pm->card->last4 . '_' .
                $pm->card->exp_month . '_' .
                $pm->card->exp_year;

            // Skip if already added
            if (isset($seenCards[$fingerprintKey])) {
                continue;
            }

            // Mark as seen
            $seenCards[$fingerprintKey] = true;

            $savedCards[] = [
                'id'        => $pm->id,
                'brand'     => ucfirst($pm->card->brand),
                'last4'     => $pm->card->last4,
                'exp_month' => $pm->card->exp_month,
                'exp_year'  => $pm->card->exp_year,
            ];
        }

        // $info reflects only the LAST cart item's slot by the time it reaches here (the caller's
        // loop reassigns it per item) — the real per-item tour dates survive in $data['data'][*]['date']
        // (built per-item by CartService::prepareCartBookingData()), so resolve the mixed-cart mode
        // from THAT, not from $info->date. Per the mixed-cart decision, any 2027+ item in the cart
        // makes the whole charge settle platform-held.
        $mode = app(PaymentModeResolver::class)->resolve(array_column($data['data'] ?? [], 'date'));

        // Create payment intent
        $paymentIntentParams = [
            'payment_method_types' => ['card'], // 'link'
            'statement_descriptor_suffix' => $statementDescriptor,
            'currency' => currencyFormation(code: true),
            'amount' => $amounts['stripeTotal'] * 100,
            'description' => $amounts['description'],
            'customer' => $customer->id,
            'metadata' => [
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'email' => $data['customer_email'],
                'split_payment' => !empty($data['split_value']) ? 1 : 0,
                'tenant_id' => tenant()->id,
            ],
            ...app(PaymentGatewayInterface::class)->buildConnectedPaymentData($info->stripe_destination_id, (int) round($amounts['stripeFee'] * 100), mode: $mode)
        ];

        // This method never set transfer_group explicitly, in either mode -- for a DESTINATION
        // charge that went unnoticed, because Stripe auto-generates one itself for any charge that
        // carries transfer_data, if the caller doesn't supply one. That auto-generation only
        // happens when transfer_data is present, so a platform-held charge (no transfer_data at
        // all) silently ended up with no transfer_group whatsoever -- fine for a non-partner cart,
        // but ThreeWayPaymentService::transferToPartner() (called downstream for any partner
        // package in the cart, regardless of settlement mode) reads $paymentIntent->transfer_group
        // back off this charge and would get null, then send Stripe an explicit empty string,
        // which Stripe rejects outright. Set it explicitly here for the same two cases as the
        // other creation choke points: destination mode (matches what Stripe would have generated
        // anyway) or any partner package in the cart.
        $cartHasPartnerPackage = array_sum(array_column($data['data'] ?? [], 'partnerFeesTotal')) > 0;
        if ($mode !== PaymentModeResolver::PLATFORM_HELD || $cartHasPartnerPackage) {
            $paymentIntentParams['transfer_group'] = generateTransferGroup();
        }

        // Set setup_future_usage if saving card
        if (isset($data['save_card']) && $data['save_card'] === '1') {
            $paymentIntentParams['setup_future_usage'] = 'off_session';
        }

        $paymentIntent = app(PaymentGatewayInterface::class)->createPayment($paymentIntentParams);

        return [
            'clientSecret' => $paymentIntent->client_secret,
            'savedCards' => $savedCards
        ];
    }

    /**
     * Generate Stripe payment attributes for card reader in the app.
     *
     * @param object $request Request object containing payment details.
     * @param float $convertedAmount Converted amount to charge.
     * @param float $destinationAmount Amount for the destination account.
     * @param string $tourOperatorName Name of the tour operator.
     * @param string $destinationId Connected account to charge — the package's own
     *                              stripe_destination_id when set, otherwise the caller falls back
     *                              to the tour operator's own account (the package-level column is
     *                              an optional per-package override, not always populated).
     * @param object $customer Customer model instance.
     * @param string $mode One of PaymentModeResolver::DESTINATION/PLATFORM_HELD — resolved by the
     *                     caller from the booking's (or cart's) tour date(s); defaults to DESTINATION
     *                     so forgetting it reproduces today's exact behavior.
     * @return array Stripe payment attributes.
     */
    public static function getStripeAttributesForAppCardReader(
        object $request,
        float $convertedAmount,
        float $destinationAmount,
        string $tourOperatorName,
        string $destinationId,
        object $customer,
        string $mode = PaymentModeResolver::DESTINATION
    ): array {
        return [
            'payment_method_types' => ['card_present'],
            'capture_method' => 'automatic',
            'currency' => currencyFormation(code: true),
            'amount' => $convertedAmount * 100,
            'statement_descriptor_suffix' => $tourOperatorName,
            'customer' => $customer->stripe_id,
            'expand' => ['charges.data.transfer'],
            ...app(PaymentGatewayInterface::class)->buildConnectedPaymentData($destinationId, (int) round($destinationAmount * 100), mode: $mode)
        ];
    }

    /**
     * Prepare metadata for payment processing for card reader in app.
     *
     * @param object $request Request object.
     * @param object $customer Customer model instance.
     * @param array $cartBookingsData Booking data for the cart.
     * @return array Metadata array.
     */
    public static function prepareMetadataForAppCardReader(
        object $request,
        object $customer,
        array $cartBookingsData
    ): array {
        return [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone_number,
            'data' => $cartBookingsData,
            'split_value' => '',
            'app' => Constants::STATUS_ONE,
            'section' => Config::get('constants.payment_sections.' . $request->section),
            'from' => 'iOS Card Reader',
        ];
    }
    /**
     * Generate Stripe metadata for app card reader.
     *
     * @param object $request Request object.
     * @param object $tourBooking Tour booking details.
     * @param object $tourPackage Tour package details.
     * @param object $customer Customer model instance.
     * @param float $convertedAmount Converted amount to charge.
     * @param float $destinationAmount Amount for the destination account.
     * @param string $discountFixed Fixed discount amount.
     * @param string $discountPercentage Discount percentage.
     * @return array Stripe metadata.
     */
    public static function getStripeMetadataForAppCardReader(
        object $request,
        object $tourBooking,
        object $tourPackage,
        object $customer,
        float $convertedAmount,
        float $destinationAmount,
        string $discountFixed,
        string $discountPercentage
    ): array {
        $section = Config::get('constants.payment_sections.' . $request->section);
        $touristsCount = $tourBooking->tourists + $tourBooking->infants;
        $metaData = [
            'description' => "Booking Id: {$request->booking_id}\n"
                . "Tour Package: {$tourPackage->name}\n"
                . "Tour Date: {$tourBooking->tourSlotTime->tourSlotDate->date}\n"
                . "Tour Time: {$tourBooking->tourSlotTime->time_from}\n"
                . "Tourists: {$touristsCount}\n"
                . "Email: {$customer->email}\n"
                . "From: iOS Card Reader",
            'metadata' => [
                'booking_id' => $request->booking_id,
                'tour_package' => $tourPackage->name,
                'tour_date' => $tourBooking->tourSlotTime->tourSlotDate->date,
                'tour_time' => $tourBooking->tourSlotTime->time_from,
                'tourists' => $tourBooking->tourists + $tourBooking->infants,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone_number,
                'email' => $customer->email,
                'amount' => $convertedAmount,
                'fee' => $destinationAmount,
                'split' => !empty($request->split_value) ? 1 : 0,
                'is_pax_added' => !empty($request->is_pax_added) ? 1 : 0,
                'from' => 'iOS Card Reader',
                'section' => $section,
                'discount_fixed' => $discountFixed,
                'discount_percentage' => $discountPercentage,
                'tenant_id' => tenant()->id,
            ],
        ];

        if ($section !== Config::get('constants.payment_sections.change_people')) {
            $metaData['metadata']['app'] = Constants::STATUS_ONE;
        }

        return $metaData;
    }

    /**
     * Create a Stripe Payment Intent For Edit Discount and Custom Charge.
     *
     * @param array $stripeDetails
     * @param array $data
     * @param object $info
     * @return array
     */
    public static function createStripePaymentIntentCommon(array $stripeDetails, array $data, object $info, string $mode): array
    {
        $statementDescriptor = $info->statement_descriptor ?: self::buildDescriptorFallback($info->tour_operator, $info->tour_package);

        // Check if customer exists or create a new one
        $customerEmail = $data['customer_email'];
        $customer = self::stripeCustomer($customerEmail, $data);

        // Retrieve saved payment methods
        $savedCards = [];
        $seenCards  = [];
        $pmParams = ['customer' => $customer->id, 'type' => 'card'];
        $paymentMethods = app(PaymentGatewayInterface::class)->listPaymentMethods($pmParams);

        // Get allowed payment_method_ids from DB
        $dbSavedCards = SaveCreditCard::where('customer_id', $customer->id)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('payment_method_id')
            ->flip()
            ->toArray();

        foreach ($paymentMethods->data as $pm) {

            // Only allow cards that exist in DB table
            if (!isset($dbSavedCards[$pm->id])) {
                continue;
            }

            // Create unique fingerprint
            $fingerprintKey = strtolower($pm->card->brand) . '_' .
                $pm->card->last4 . '_' .
                $pm->card->exp_month . '_' .
                $pm->card->exp_year;

            // Skip if already added
            if (isset($seenCards[$fingerprintKey])) {
                continue;
            }

            // Mark as seen
            $seenCards[$fingerprintKey] = true;

            $savedCards[] = [
                'id'        => $pm->id,
                'brand'     => ucfirst($pm->card->brand),
                'last4'     => $pm->card->last4,
                'exp_month' => $pm->card->exp_month,
                'exp_year'  => $pm->card->exp_year,
            ];
        }

        $payoutAmount = $stripeDetails['payoutAmount'] ?? 0.00;
        $paymentIntentParams = [
            'payment_method_types' => ['card'], // 'link'
            'statement_descriptor_suffix' => $statementDescriptor,
            'currency' => currencyFormation(code: true),
            'amount' => $stripeDetails['stripeTotal'] * 100,
            'description' => $stripeDetails['description'],
            'customer' => $customer->id,
            'metadata' => [
                'amount' => $stripeDetails['stripeTotal'],
                'platform_fee' => $stripeDetails['stripeFee'] ?? 0.00,
                'partner_fee' => 0.00,
                'payout_amount' => $payoutAmount,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'customer_email' => $data['customer_email'],
                'booking_id' => $stripeDetails['BookingId'],
                'tour_package' => $info->tour_package,
                'tour_date' => $info->date,
                'tour_time' => $info->time_from,
                'tourists' => $stripeDetails['tourists'],
                'tenant_id' => tenant()->id,
            ],
            ...app(PaymentGatewayInterface::class)->buildConnectedPaymentData($info->stripe_destination_id, (int) round($stripeDetails['stripeFee'] * 100), mode: $mode)
        ];

        // transfer_group only matters for reconciling a later, separate Transfer against this
        // charge -- the partner/tour-guide split engine (ThreeWayPaymentService) reads it back off
        // the charge regardless of settlement mode, since it runs on is_partner_booking alone. A
        // non-partner platform-held (2027+) charge never has any Transfer created against it, so
        // the tag is unused noise on Stripe's side -- omit it there. $payoutAmount is only ever
        // populated by callers for a partner booking (see StripeService.php), so it's already the
        // right signal here without a new parameter.
        if ($mode !== PaymentModeResolver::PLATFORM_HELD || $payoutAmount > 0) {
            $paymentIntentParams['transfer_group'] = generateTransferGroup();
        }

        // Set setup_future_usage if saving card
        if (isset($data['save_card']) && $data['save_card'] === '1') {
            $paymentIntentParams['setup_future_usage'] = 'off_session';
        }

        $paymentIntent = app(PaymentGatewayInterface::class)->createPayment($paymentIntentParams);

        return [
            'clientSecret' => $paymentIntent->client_secret,
            'savedCards' => $savedCards
        ];
    }

    /**
     * Create a Stripe Payment Intent.
     *
     * @param string $statementDescriptor
     * @param float $stripeTotal
     * @param float $stripeFees
     * @param string $description
     * @param array $metaData
     * @param string $destinationId
     * @return array
     */
    private static function createStripeIntent($statementDescriptor, $stripeTotal, $stripeFees, $description, $metaData, $destinationId, $email = '', $idempotencyKey = null, string $mode = PaymentModeResolver::DESTINATION, bool $isPartnerBooking = false)
    {
        // Check if customer exists or create a new one
        $customerEmail = ($email != '') ? $email : $metaData['email'];

        $customer = self::stripeCustomer($customerEmail, $metaData);

        // Retrieve saved payment methods
        $savedCards = [];
        $seenCards  = [];
        $pmParams = ['customer' => $customer->id, 'type' => 'card'];
        $paymentMethods = app(PaymentGatewayInterface::class)->listPaymentMethods($pmParams);

        // Get allowed payment_method_ids from DB
        $dbSavedCards = SaveCreditCard::where('customer_id', $customer->id)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('payment_method_id')
            ->flip()
            ->toArray();

        foreach ($paymentMethods->data as $pm) {

            // Only allow cards that exist in DB table
            if (!isset($dbSavedCards[$pm->id])) {
                continue;
            }

            // Create unique fingerprint
            $fingerprintKey = strtolower($pm->card->brand) . '_' .
                $pm->card->last4 . '_' .
                $pm->card->exp_month . '_' .
                $pm->card->exp_year;

            // Skip if already added
            if (isset($seenCards[$fingerprintKey])) {
                continue;
            }

            // Mark as seen
            $seenCards[$fingerprintKey] = true;

            $savedCards[] = [
                'id'        => $pm->id,
                'brand'     => ucfirst($pm->card->brand),
                'last4'     => $pm->card->last4,
                'exp_month' => $pm->card->exp_month,
                'exp_year'  => $pm->card->exp_year,
            ];
        }

        unset($metaData['email'], $metaData['customer_name'], $metaData['customer_phone']);
        // Create Stripe Payment Intent
        $paymentIntentParams = [
            'payment_method_types' => ['card'], // 'link'
            'statement_descriptor_suffix' => $statementDescriptor,
            'currency' => currencyFormation(code: true),
            'amount' => $stripeTotal * 100, // Convert to cents
            'customer' => $customer->id,
            'description' => $description,
            'metadata' => [
                'bookings' => json_encode($metaData),
                'tenant_id' => tenant()->id,
            ],
            ...app(PaymentGatewayInterface::class)->buildConnectedPaymentData($destinationId, (int) round($stripeFees * 100), mode: $mode)
        ];

        // See the matching comment in createStripePaymentIntentCommon() -- same reasoning, this is
        // the shared choke point for New Booking/Make Payment/Combo Payment/Edit Booking.
        if ($mode !== PaymentModeResolver::PLATFORM_HELD || $isPartnerBooking) {
            $paymentIntentParams['transfer_group'] = generateTransferGroup();
        }

        // Set setup_future_usage if saving card
        if (isset($metaData['save_card']) && $metaData['save_card'] === '1') {
            $paymentIntentParams['setup_future_usage'] = 'off_session';
        }

        $opts = $idempotencyKey ? ['idempotency_key' => $idempotencyKey] : [];
        $paymentIntent = app(PaymentGatewayInterface::class)->createPayment($paymentIntentParams, $opts);

        return [
            'clientSecret'    => $paymentIntent->client_secret,
            'paymentIntentId' => $paymentIntent->id,
            'savedCards'      => $savedCards,
        ];
    }

    /**
     * Process a Stripe payment intent for change people.
     *
     * @param object $tourBooking
     * @param object $info
     * @param object $customer
     * @param float $stripeTotal
     * @param float $stripeFees
     * @param float $partnerFeesTotal
     * @param float $payoutAmount
     * @return array
     */
    public static function createStripePaymentIntentEditBooking(object $tourBooking, object $info, object $customer, float $stripeTotal, float $stripeFees, string $by, $partnerFeesTotal = 0.00, $payoutAmount = 0.00): array
    {
        // Below code is used to send to stripe
        $description = self::generateDescriptionForEditBooking($info, $tourBooking);
        $metaData = self::prepareMetaDataForEditBooking($info, $customer, $tourBooking, $stripeTotal, $partnerFeesTotal, $stripeFees, $payoutAmount, $by);

        // Set the statement descriptor
        $statementDescriptor = ($tourBooking->statement_descriptor ?? $info->statement_descriptor) ?: self::buildDescriptorFallback($info->tour_operator, $info->package_name);

        $mode = app(PaymentModeResolver::class)->resolveForSingleDate($info->date);
        $paymentIntent = self::createStripeIntent($statementDescriptor, $stripeTotal, $stripeFees, $description, $metaData, $info->stripe_destination_id, mode: $mode, isPartnerBooking: $partnerFeesTotal > 0);

        return [
            'clientSecret' => $paymentIntent['clientSecret'],
            'savedCards' => $paymentIntent['savedCards']
        ];
    }
    /**
     * Prepare metadata for the Stripe payment.
     *
     * @param object $info
     * @param object $customer
     * @param object $tourBooking
     * @param float $stripeTotal
     * @return array
     */
    private static function prepareMetaDataForEditBooking(object $info, object $customer, object $tourBooking, float $stripeTotal, $stripeFees, $partnerFees, $payoutAmount, string $by): array
    {
        return [
            'tour_package' => $info->package_name,
            'tour_date' => $info->date,
            'tour_time' => $info->time_from,
            'tourists' => $tourBooking->tourists + $tourBooking->infants,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone_number,
            'email' => $customer->email,
            'amount' => $stripeTotal,
            'platform_fee' => $stripeFees ?? 0.00,
            'partner_fee' => $partnerFees ?? 0.00,
            'payout_amount' => $payoutAmount ?? 0.00,
            "by" => $by
        ];
    }

    /**
     * Generate a description string for the payment.
     *
     * @param object $info
     * @param object $tourBooking
     * @return string
     */
    private static function generateDescriptionForEditBooking(object $info, object $tourBooking): string
    {
        return sprintf(
            "Tour Package: %s\nTour Date: %s\nTour Time: %s\nTourists: %d\nEmail: %s",
            $info->package_name,
            $info->date,
            $info->time_from,
            $tourBooking->tourists + $tourBooking->infants,
            $tourBooking->customer_email
        );
    }
    /**
     * Generate metadata for Stripe payments for change history payment type.
     *
     * @param object $info The booking info object.
     * @param int $tourBookingID The ID of the tour booking.
     *
     * @return array The formatted Stripe metadata.
     */
    public static function generateStripeMetadataForChangePaymentType(object $info, int $tourBookingID, string $from): array
    {
        return [
            'description' => sprintf(
                "Booking Id: %d\nTour Package: %s\nTour Date: %s\nTour Time: %s\nTourists: %s\nEmail: %s\nFrom: Change Payment Method - " . $from,
                $tourBookingID,
                $info->package_name,
                $info->date,
                $info->time_from,
                $info->tourists + $info->infants,
                $info->customeremail
            ),
            'metadata' => [
                'booking_id' => $info->id,
                'tour_package' => $info->package_name,
                'tour_date' => $info->date,
                'tour_time' => $info->time_from,
                'tourists' => $info->tourists + $info->infants,
                'customer_name' => $info->customername,
                'customer_phone' => $info->customerphone,
                'email' => $info->customeremail,
                'tenant_id' => tenant()->id,
            ]
        ];
    }
    /**
     * Generate Stripe payment attributes for tap and pay card reader in the app.
     *
     * @param float $convertedAmount Converted amount to charge.
     * @param float $feesAmount  Tip fees account.
     * @param string $tourOperatorName Name of the tour operator.
     * @param object $tourPackage Tour package details.
     * @param object $customer Customer model instance.
     * @return array Stripe payment attributes.
     */
    public static function getStripeAttributesForAppTapToPay(
        float $convertedAmount,
        float $feesAmount,
        string $tourOperatorName,
        string $stripeDestinationId,
    ): array {
        return [
            'payment_method_types' => ['card_present'],
            'capture_method' => 'manual',
            'confirmation_method' => 'automatic',
            'currency' => currencyFormation(code: true),
            'amount' => $convertedAmount * 100,
            'statement_descriptor_suffix' => $tourOperatorName,
            'expand' => ['charges.data.transfer'],
            // Tips are explicitly excluded from Payout Simplification — always DESTINATION,
            // regardless of the tour date, per the locked-in decision to defer that call.
            ...app(PaymentGatewayInterface::class)->buildConnectedPaymentData($stripeDestinationId, (int) round($feesAmount * 100), mode: PaymentModeResolver::DESTINATION)
        ];
    }
    /**
     * Generate Stripe metadata for app card reader.
     *
     * @param object $guideInfo Guide info object.
     * @param float $convertedAmount Converted tip amount to charge.
     * @param float $destinationAmount Tip fees amount.
     * @param float $tipAmount Tip amount.
     * @param string $discountPercentage Discount percentage.
     * @return array Stripe metadata.
     */
    public static function getStripeMetadataForAppTapToPay(
        object $guideInfo,
        float $convertedAmount,
        float $destinationAmount,
        float $tipAmount
    ): array {
        $section = Config::get('constants.payment_sections.tap_to_pay_tip_payment');

        $metaData = [
            'description' => "Guide Id: {$guideInfo->id}\n"
                . "Guide Name: {$guideInfo->first_name} {$guideInfo->last_name}\n"
                . "From: iOS Tap to Pay",
            'metadata' => [
                'guide_id' => $guideInfo->id,
                'guide_name' => $guideInfo->first_name . " " . $guideInfo->last_name,
                'tip_amount' => $tipAmount,
                'fee' => $destinationAmount,
                'total_amount' => $convertedAmount,
                'from' => 'iOS Tap to Pay',
                'section' => $section,
                'tenant_id' => tenant()->id,
            ],
        ];

        if ($section !== Config::get('constants.payment_sections.change_people')) {
            $metaData['metadata']['app'] = Constants::STATUS_ONE;
        }

        return $metaData;
    }
    /**
     * Generate Stripe payment attributes for creating tip from tour guide dashboard.
     *
     * @param float $convertedAmount Converted amount to charge.
     * @param float $feesAmount  Tip fees account.
     * @param string $tourOperatorName Name of the tour operator.
     * @param object $tourPackage Tour package details.
     * @param object $customer Customer model instance.
     * @return array Stripe payment attributes.
     */
    public static function getStripeAttributesForCreateTip(
        float $convertedAmount,
        float $feesAmount,
        string $tourOperatorName,
        string $stripeDestinationId,
    ): array {
        return [
            'payment_method_types' => ['card'],
            'currency' => currencyFormation(code: true),
            'amount' => $convertedAmount * 100,
            'statement_descriptor_suffix' => $tourOperatorName,
            'expand' => ['charges.data.transfer'],
            // Tips are explicitly excluded from Payout Simplification — always DESTINATION,
            // regardless of the tour date, per the locked-in decision to defer that call.
            ...app(PaymentGatewayInterface::class)->buildConnectedPaymentData($stripeDestinationId, (int) round($feesAmount * 100), mode: PaymentModeResolver::DESTINATION)
        ];
    }
    /**
     * Generate Stripe metadata for for creating tip from tour guide dashboard.
     *
     * @param object $guideInfo Guide info object.
     * @param float $convertedAmount Converted tip amount to charge.
     * @param float $destinationAmount Tip fees amount.
     * @param float $tipAmount Tip amount.
     * @param string $discountPercentage Discount percentage.
     * @return array Stripe metadata.
     */
    public static function getStripeMetadataForCreateTip(
        object $guideInfo,
        float $convertedAmount,
        float $destinationAmount,
        float $tipAmount
    ): array {

        $metaData = [
            'description' => "Guide Id: {$guideInfo->id}\n"
                . "Guide Name: {$guideInfo->first_name} {$guideInfo->last_name}\n"
                . "From: Create Tip - Guide Dashboard",
            'metadata' => [
                'guide_id' => $guideInfo->id,
                'guide_name' => $guideInfo->first_name . " " . $guideInfo->last_name,
                'tip_amount' => $tipAmount,
                'fee' => $destinationAmount,
                'total_amount' => $convertedAmount,
                'from' => 'Create Tip - Guide Dashboard',
                'tenant_id' => tenant()->id,
            ],
        ];

        return $metaData;
    }

    /**
     * Generate the save card from stripe and database collcetion.
     *
     * @param object $info 
     */
    public static function saveCards(object $info): array
    {
        $customerEmail = $info->email;

        $listParams = ['email' => $customerEmail];
        $customers = app(PaymentGatewayInterface::class)->listCustomers($listParams);

        if (empty($customers->data)) {
            return ['data' => []];
        }

        $customer = $customers->data[0];

        // Get Stripe Payment Methods
        $pmParams = ['customer' => $customer->id, 'type' => 'card'];
        $paymentMethods = app(PaymentGatewayInterface::class)->listPaymentMethods($pmParams);

        // Create Stripe PM lookup
        $stripeCards = [];
        foreach ($paymentMethods->data as $pm) {
            $stripeCards[$pm->id] = $pm;
        }

        // Fetch full DB records (not deleted)
        $dbCards = SaveCreditCard::where('customer_id', $customer->id)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get()
            ->unique('payment_method_id')
            ->values();

        $savedCards = [];

        foreach ($dbCards as $card) {

            // Only include if exists in Stripe
            if (!isset($stripeCards[$card->payment_method_id])) {
                continue;
            }

            $pm = $stripeCards[$card->payment_method_id];
            $userName = HistoryService::resolveUserName($card->created_by, $card->created_by_user_type);

            $savedCards[] = [
                'id'                    => $pm->id,
                'brand'                 => getCardBrandLogo($pm->card->brand),
                'last4'                 => $pm->card->last4,
                'exp_month'             => $pm->card->exp_month,
                'exp_year'              => $pm->card->exp_year,

                // Full DB details
                'request_from'          => $card->request_from,
                'created_by'            => $card->created_by,
                'created_by_user_type'  => $userName . ' (' . $card->created_by_user_type . ')',
                'created_at'            => $card->created_at->format('m-d-Y h:i A'),
            ];
        }

        return [
            'data' => $savedCards
        ];
    }

    /**
     * Create and retrieve the customer id and also update the old record of customer id from the stripe.
     *
     * @param string $customerEmail 
     * @param object $stripe 
     */
    private static function buildDescriptorFallback(string $operatorName, string $tourName = ''): string
    {
        $raw = trim($operatorName . ' ' . $tourName);
        $result = trim(substr(strtoupper(preg_replace('/[^A-Za-z0-9 \-.]/', '', $raw)), 0, 22));
        return $result ?: strtoupper(tenant()->id);
    }

    public static function stripeCustomer($customerEmail, $metaData)
    {
        $gateway = app(PaymentGatewayInterface::class);

        // Retrive the customers from stripe and fetch the latest one
        $listParams = ['email' => $customerEmail, 'limit' => 1];
        $retriveFirstFromAllCustomer = $gateway->listCustomers($listParams);
        // Retrive the customer from the NAT tenant database
        $natCustomer = Customer::where('email', $customerEmail)->first();

        // $metaData is a flat metadata array on most flows, but the combo cart payment
        // (createStripePaymentIntentMakeComboPayment) passes a numeric LIST of per-booking
        // rows — every booking in a cart belongs to the same customer, so the first row's
        // details are authoritative there.
        $customerMeta = array_is_list($metaData) ? ($metaData[0] ?? []) : $metaData;

        $newCustomerParams = [
            'email' => $customerEmail,
            'name'  => $customerMeta['customer_name'] ?? '',
            'phone' => $customerMeta['customer_phone'] ?? '',
        ];

        // If stripe ID is present in the tenant database customer object
        if ($natCustomer && $natCustomer->stripe_id) {
            // Retrive using the NAT db stripe id from the stripe API to get the customer data from stripe
            $retriveStripeCustomer = null;

            try {
                $retriveStripeCustomer = $gateway->retrieveCustomer($natCustomer->stripe_id, []);
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                // Customer does not exist in this mode or account
                $retriveStripeCustomer = null;
            }
            // If customer is not present then check for all customers from stripe
            if ($retriveStripeCustomer == null && count($retriveFirstFromAllCustomer->data) > 0) {
                $customer = $retriveFirstFromAllCustomer->data[0];
                $natCustomer->stripe_id = $customer->id;
                $natCustomer->save();
            } elseif ($retriveStripeCustomer == null) {
                // No customer found in stripe then create it
                $customer = $gateway->createCustomer($newCustomerParams);
            } else {
                // If found the customer in stripe then just fetch
                $customer = $retriveFirstFromAllCustomer->data[0];
                // If customer table stripe_id is not same then update
                if ($customer->id != $natCustomer->stripe_id) {
                    $natCustomer->stripe_id = $customer->id;
                    $natCustomer->save();
                }
            }
        } elseif (count($retriveFirstFromAllCustomer->data) > 0) {
            // If NAT db don't have stripe ID in customer or customer is not present in DB
            // then check in the stripe all customers for the record
            // If found then only fetch the data for below use cases
            $customer = $retriveFirstFromAllCustomer->data[0];

            // Update the old record if customer id is null in the customer table
            if ($natCustomer && $natCustomer->stripe_id == null) {
                $natCustomer->stripe_id = $customer->id;
                $natCustomer->save();
            }
        } else {
            // And if stripe and nat both don't have a customer then create a new one
            $customer = $gateway->createCustomer($newCustomerParams);
        }

        return $customer;
    }
}
