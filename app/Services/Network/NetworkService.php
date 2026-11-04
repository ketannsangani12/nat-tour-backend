<?php

namespace App\Services\Network;

use App\Constants\Constants;
use App\Models\Network;
use App\Models\NetworkTenant;
use App\Models\Tenant;
use App\Models\TourBooking;
use App\Models\TourPackage;
use App\Models\TourPackageForm;
use App\Models\TourPromotion;
use App\Services\Network\Slot\SlotTimeService;
use App\Services\Network\Affiliate\AffiliateService;
use App\Services\Network\Package\PackageService;
use App\Services\Network\Package\PackageRelationService;
use App\Services\Network\Package\PackageRateService;
use App\Services\TimeSlot\TouristService;
use App\Services\Network\Booking\BookingService;
use App\Services\Network\Stripe\StripeService;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Cache;
use Exception;
use Illuminate\Support\Facades\DB;

class NetworkService
{
    /**
     * @var SlotTimeService
     */
    protected $slotTimeService;

    /**
     * @var AffiliateService
     */
    protected $affiliateService;

    /**
     * @var PackageService
     */
    protected $packageService;

    /**
     * @var PackageRelationService
     */
    protected $packageRelationService;

    /**
     * @var PackageRateService
     */
    protected $packageRateService;

    /**
     * @var TouristService
     */
    protected $touristService;

    /**
     * @var BookingService
     */
    protected $bookingService;

    /**
     * @var StripeService
     */
    protected $stripeService;

    public function __construct(
        SlotTimeService $slotTimeService,
        AffiliateService $affiliateService,
        PackageService $packageService,
        PackageRelationService $packageRelationService,
        PackageRateService $packageRateService,
        TouristService $touristService,
        BookingService $bookingService,
        StripeService $stripeService
    ) {
        $this->slotTimeService = $slotTimeService;
        $this->affiliateService = $affiliateService;
        $this->packageService = $packageService;
        $this->packageRelationService = $packageRelationService;
        $this->packageRateService = $packageRateService;
        $this->touristService = $touristService;
        $this->bookingService = $bookingService;
        $this->stripeService = $stripeService;
    }

    /**
     * Detect and fetch the current network based on domain or request slug.
     *
     * @return Network|null
     */
    public function getCurrentNetwork()
    {
        $host = Request::getHost();
        $host = "nat-network";

        return Cache::remember("current_network_{$host}", 60, fn() => Network::where('slug', $host)
            ->where('status', 1)
            ->first());
    }

    /**
     * Get active tenants for the current network.
     *
     * @return array
     */
    public function getTenants()
    {
        $network = $this->getCurrentNetwork();

        if (!$network) {
            throw new Exception("Network not found for this request", 404);
        }

        return NetworkTenant::where('network_id', $network->id)
            ->where('status', 1)
            ->pluck('tenant_id')
            ->toArray();
    }

    /**
     * Fetch packages from all connected tenants.
     *
     * @return array
     */
    public function getPackages(): array
    {
        $tenants = $this->getTenants();

        $packages = [];

        foreach ($tenants as $tenantId) {
            $packages[] = $this->fetchTenantPackages($tenantId);
        }

        return $packages;
    }

    /**
     * Fetch packages from a specific tenant (via API or direct DB connection)
     *
     * @param string $tenantId
     * @return array
     */
    protected function fetchTenantPackages(string $tenantId): array
    {
        $tenant = Tenant::findOrFail($tenantId);
        tenancy()->initialize($tenant);

        $affiliate = $this->affiliateService->getAffiliate();

        $packages = [];
        if ($affiliate) {
            $assignedPackages = $this->packageService->getAssignedPackages($affiliate->id);
            $packages = ($assignedPackages->isEmpty()) ? [] : TourPackage::active()->whereIn('id', $assignedPackages->pluck('package_id'))->get()->toArray();
        }

        tenancy()->end();

        return [
            'tenant_id' => $tenantId,
            'packages' => $packages
        ];
    }

    /**
     * Fetch a specific package from a connected tenant.
     *
     * @param string $tenantId
     * @param int $packageId
     * @return array
     */
    public function getPackageDetails(string $tenantId, int $packageId): array
    {
        $tenant = Tenant::findOrFail($tenantId);
        tenancy()->initialize($tenant);

        $tourOperator = tourOperator();
        $affiliate = $this->affiliateService->getAffiliate();

        $package = [];
        if ($affiliate) {
            $assignedPackages = $this->packageService->getAssignedPackages($affiliate->id);
            $package = TourPackage::active()->whereIn('id', $assignedPackages->pluck('package_id'))->where('id', $packageId)->first();
            $package->timezone = $tourOperator->timezone;
            $package = $package->toArray();
        }

        tenancy()->end();

        return [
            'tenant_id' => $tenantId,
            'package' => $package
        ];
    }

    /**
     * Fetch available time slots for a specific package.
     *
     * @param string $tenantId
     * @param int $packageId
     * @param string $requestDate
     *
     * @return array
     */
    public function getTimeSlots(string $tenantId, int $packageId, string $requestDate): array
    {
        $tenant = Tenant::findOrFail($tenantId);
        tenancy()->initialize($tenant);

        $tourPackage = TourPackage::findOrFail($packageId);
        $affiliate = $this->affiliateService->getAffiliate();

        // Calculate block date and time based on affiliate duration
        [$blockDate, $blockTime] = $this->slotTimeService->calculateBlockTime($affiliate);

        // Fetch slot times and affiliate-specific time slots
        $slotTimes = $this->slotTimeService->getSlotTimes(
            $packageId,
            $affiliate->id,
            $requestDate,
            $blockDate,
            $blockTime,
            $tourPackage->package_has_slots
        );

        $affiliateTimeSlots = $this->affiliateService->getAffiliateTimeSlots($affiliate->id, $packageId);

        // If package relation setting is enabled, fetch package families
        $packageRelationSetting = hasSetting('PACKAGE_RELATION');
        $tourPackageFamily = $packageRelationSetting
            ? $this->packageRelationService->getPackageRelations($packageId, $requestDate)
            : [];

        $slots = [];
        foreach ($slotTimes as $key => $slot) {
            $slotDateTime = "{$requestDate} {$slot->time_from}";

            // Calculate related package tourists if applicable
            [$relationPackage, $totalTourists] = $this->packageRelationService->getRelationPackageAndTourists(
                $packageRelationSetting,
                $tourPackageFamily,
                $slot
            );

            // Find affiliate-specific seat limit for this slot
            $desiredSlot = collect($affiliateTimeSlots)->firstWhere('time_from', $slot->time_from);
            $affiliateSeatLimit = $desiredSlot->seat_limit ?? Constants::STATUS_ZERO;

            // Total booked seats — either totalTourists if package relations apply, else normal
            $bookedSeats = ($totalTourists > 0 && !empty($relationPackage))
                ? (int)$totalTourists
                : (int)$slot->tourists;

            $availableSeats = $this->slotTimeService->calculateAvailableSeats(
                $slot->seats,
                $affiliateSeatLimit,
                (int)$slot->afl_tourists,
                $bookedSeats
            );

            // Custom rate mapping check
            $customDate = $this->packageRateService->getCustomDate($packageId, $affiliate->id, $requestDate);
            $rateSlotMapping = ($customDate && $customDate->apply_on_slots)
                ? $this->packageRateService->checkRateSlotMapping($packageId, $affiliate->id, $customDate->id, $slot->time_from)
                : Constants::STATUS_ZERO;

            $slots[$key] = [
                'id'              => $slot->id,
                'date'            => $requestDate,
                'time'            => date('g:i A', strtotime($slotDateTime)),
                'slot_time'       => $slot->time_from,
                'seats'           => $availableSeats,
                'bookable_status' => $availableSeats > Constants::STATUS_ZERO ? $slot->bookable_status : 'Closed',
                'custom_rate'     => $rateSlotMapping ? $customDate->id : Constants::STATUS_ZERO,
            ];
        }

        tenancy()->end();

        return [
            'tenant_id' => $tenantId,
            'slots'     => $slots
        ];
    }

    /**
     * Fetch available rate groups for a specific package.
     *
     * @param string $tenantId
     * @param int $packageId
     * @param string $requestDate
     * @param int|null $slotTimeId
     *
     * @return array
     */
    public function getRateGroups(string $tenantId, int $packageId, string $requestDate, ?int $slotTimeId = null): array
    {
        $tenant = Tenant::findOrFail($tenantId);
        tenancy()->initialize($tenant);

        $package = TourPackage::find($packageId);
        $affiliate = $this->affiliateService->getAffiliate();
        $year = date('Y', strtotime($requestDate));
        $serviceCommissionPercentage = $this->affiliateService->getCommissionPercentage($affiliate, $requestDate);

        $customRateDate = isCustomDateRateEnabled($requestDate, $packageId, $affiliate->id, $slotTimeId);

        $tourPackageRates = DB::table('tour_package_rates as pr')
            ->where('pr.tour_package_id', $packageId)
            ->where('pr.status', '=', "Active")
            ->where('pr.deleted_by', '=', 0)
            ->where('pr.affiliate_id', $affiliate->id)
            ->when($customRateDate, fn($query) => $query->where('pr.custom_date_id', $customRateDate), fn($query) => $query->whereNull('pr.custom_date_id'))
            ->where('pr.scheduling_year', $year)
            ->select(
                'pr.id',
                'pr.rate_for',
                'pr.rate',
                'pr.tour_package_id',
                'pr.custom_date_id',
                'pr.processing_charge_percentage',
                'pr.additional_charge',
                'pr.partner_fee_type',
                'pr.partner_fee_percentage',
                'pr.partner_fee_amount',
                'pr.permit_fee',
                'pr.description',
                'pr.min_pax_allowed',
                'pr.max_pax_allowed',
                'pr.is_group_rate',
                'pr.min_group_size',
                'pr.max_group_size'
            )
            ->orderBy('pr.orderby')
            ->get();

        $tourPackageRateGroups = [];
        if ($tourPackageRates->count() > 0) {
            $minMaxPaxApply = in_array(minMaxPaxApply(), [Constants::STATUS_ONE, Constants::STATUS_THREE]);

            foreach ($tourPackageRates as $packageRates) {
                $packageRates->processing_fee = number_format((float)$packageRates->processing_charge_percentage, 2, '.', '');
                $packageRates->permit_fee = number_format((float)$packageRates->permit_fee, 2, '.', '');
                $packageRates->tax = number_format((float)$packageRates->additional_charge, 2, '.', '');
                $packageRates->min_pax_allowed = $minMaxPaxApply ? $packageRates->min_pax_allowed : 0;
                $packageRates->max_pax_allowed = $minMaxPaxApply ? $packageRates->max_pax_allowed : 0;

                if ($package->is_group_rate_enabled) {
                    for ($i = $packageRates->min_group_size; $i <= $packageRates->max_group_size; $i++) {
                        $tourPackageRateGroups[] = [
                            'id' => $packageRates->id,
                            'rate' => $packageRates->rate,
                            'tax' => $packageRates->tax,
                            'size' => $i,
                            'rate_for' => $packageRates->rate_for
                        ];
                    }
                }
            }

            if ($package->is_group_rate_enabled == 0 && $tourPackageRates->count() > 0) {
                $tourPackageRateGroups = $tourPackageRates->toArray();
            }
        }

        tenancy()->end();

        return [
            'tenant_id' => $tenantId,
            'service_commission_percentage' => $serviceCommissionPercentage,
            'rate_groups' => $tourPackageRateGroups
        ];
    }

    /**
     * Fetch custom form for a specific package.
     *
     * @param string $tenantId
     * @param int $packageId
     *
     * @return array
     */
    public function getCustomForm(string $tenantId, int $packageId): array
    {
        $tenant = Tenant::findOrFail($tenantId);
        tenancy()->initialize($tenant);

        $fields = ["id", "form_name", "description", "status", "form_package_id", "form_fields"];

        $customForm = TourPackageForm::where('form_package_id', intval($packageId))
            ->where("status", "=", "active")
            ->select($fields)
            ->limit(1)
            ->get()
            ->first();

        if ($customForm && $customForm->form_fields) {
            $customForm->form_fields = json_decode($customForm->form_fields, true);
            $customForm = $customForm->toArray();
        } else {
            $customForm = null;
        }

        tenancy()->end();

        return [
            'tenant_id' => $tenantId,
            'custom_form' => $customForm
        ];
    }

    /**
     * Set coupon for a specific package.
     *
     * @param string $tenantId
     * @param int $packageId
     * @param string $coupon
     * @param string $requestDate
     * @param int|null $tourists Optional tourist count (infants excluded), for enforcing the
     *                           promotion's own min_allowed_tourists. Fail-open when omitted --
     *                           see the check below for why.
     *
     * @return array
     */
    public function setCoupon(string $tenantId, int $packageId, string $coupon, string $requestDate, ?int $tourists = null): array
    {
        $tenant = Tenant::findOrFail($tenantId);
        tenancy()->initialize($tenant);

        $promotion = TourPromotion::where('tour_package_id', $packageId)
            ->where('code', $coupon)
            ->whereRaw("\"{$requestDate}\" between `start_date` and `end_date`")
            ->where('status', 'Active')
            ->first(['id', 'max_allowed_usages', 'min_allowed_tourists', 'discount_value', 'discount_value_type']);

        if ($promotion) {
            $bookingsCount = TourBooking::where('tour_promotion_id', $promotion->id)->count();

            if ($bookingsCount >= $promotion->max_allowed_usages && $promotion->max_allowed_usages != 0) {
                throw new Exception('Your coupon code is no longer valid.', 410);
            }

            // Optional -- partner integrations don't send this yet, so this only activates once
            // a caller actually supplies $tourists. Fail-open when omitted rather than defaulting
            // to 0 and silently rejecting every promo with a configured minimum for every partner
            // that hasn't been updated to send it. Same pattern as
            // API\V2\TourBookingController::applyCoupon().
            if ($tourists !== null && (int) $promotion->min_allowed_tourists > 0 && $tourists < (int) $promotion->min_allowed_tourists) {
                throw new Exception('This coupon requires at least ' . $promotion->min_allowed_tourists . ' tourist(s).', 422);
            }

            $promotion = $promotion->toArray();
        } else {
            throw new Exception('Your coupon code is not valid.', 404);
        }

        tenancy()->end();

        return [
            'tenant_id' => $tenantId,
            'coupon' => $promotion
        ];
    }

    /**
     * Create payment intent
     * 
     * @param array $request
     * 
     * @return array
     */
    public function createPaymentIntent($request): array
    {
        $paymentIntent = $this->stripeService->createPaymentIntent($request);

        return [
            'tenant_id' => $request['tenantId'],
            'payment_intent' => $paymentIntent
        ];
    }

    /**
     * Create bookings
     * 
     * @param array $request
     * 
     * @return array
     */
    public function createBookings($request): array
    {
        $results = [];

        foreach ($request['cartItems'] as $cartItem) {
            $tenantId = $cartItem['tenantId'];

            $tenant = Tenant::findOrFail($tenantId);
            tenancy()->initialize($tenant);

            $customerInfo = $request['customerInfo'];

            $result = $this->bookingService->createBookingForTenant($cartItem, $customerInfo);

            $results[] = $result;

            tenancy()->end();
        }

        return $results;
    }
}
