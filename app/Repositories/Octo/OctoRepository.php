<?php

namespace App\Repositories\Octo;

use App\Helpers\OctoErrorResponse;
use App\Jobs\SendOctoWebhookJob;
use App\Models\BookingTransact;
use App\Models\OctoBooking;
use App\Models\OctoWebhook;
use App\Models\TourBooking;
use App\Repositories\Repository;
use App\Services\Octo\OctoLegacyBookingBackfillService;
use App\Services\Octo\OctoService;
use App\Services\TimeSlot\TimeSlotAvailabilityService;
use App\Services\TimeSlot\TimeSlotService;
use App\Services\Booking\BookingService;
use App\Services\Booking\HistoryService;
use App\Utils\BokunUtils;
use App\Services\BokunService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Models\Category;
use App\Models\Customer;
use App\Models\PackageAffiliateAssignment;
use App\Models\TourOperator;
use App\Models\TourPackage;
use App\Models\TourPackageRate;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;
use RuntimeException;

class OctoRepository extends Repository
{
    private const VALID_NOTIFICATION_EVENTS = [
        'PRODUCT_UPDATE',
        'AVAILABILITY_UPDATE',
        'BOOKING_UPDATE',
    ];

    public $tourOperator = TourOperator::class;

    /**
     * GET /supplier
     *
     * Returns the supplier details for this tenant in OCTO format.
     */
    public function getSupplier(): JsonResponse
    {
        try {
            $operator = TourOperator::with('city.state.country')->first();

            if (!$operator) {
                return OctoErrorResponse::unprocessableEntity('Supplier not found!!');
            }

            $baseUrl = rtrim(config('app.url'), '/') . '/api/v1/octo';

            $city    = $operator->city;
            $state   = $city?->state;
            $country = $state?->country;

            $address = implode(', ', array_filter([
                $operator->address,
                $city?->name,
                $state?->name,
                $country?->name,
                $operator->postal_code,
            ])) ?: null;

            $supplier = [
                'id'       => (string) $operator->tenant_id,
                'name'     => $operator->name,
                'endpoint' => $baseUrl,
                'contact'  => [
                    'website'   => $operator->website,
                    'email'     => $operator->email,
                    'telephone' => OctoService::formatPhoneE164($operator->phone_number, $country?->phone_code),
                    'address'   => $address,
                ],
            ];

            if (OctoService::hasCapability('content')) {
                $supplier['shortDescription'] = $operator->shortdesc ?? $operator->description;
                $supplier['media']            = $this->buildOperatorMedia($operator);
            }

            return response()->json($supplier);
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    /**
     * GET /products
     */
    public function getProductList(): JsonResponse
    {
        try {
            $affiliate = $this->getAffiliateByReseller();
            if ($affiliate instanceof JsonResponse) return $affiliate;

            $assignedPackageIds = PackageAffiliateAssignment::where('affiliate_id', $affiliate->id)
                ->pluck('package_id');

            if ($assignedPackageIds->isEmpty()) {
                return OctoErrorResponse::unprocessableEntity("No packages assigned to reseller '{$affiliate->name}'.");
            }

            $operator = TourOperator::with('city.state.country')->first();
            $country  = $operator?->city?->state?->country;

            $packages = TourPackage::whereIn('id', $assignedPackageIds)->active()->get();

            $today    = now($operator?->timezone ?? 'UTC')->toDateString();
            $todayYear = (int) substr($today, 0, 4);

            $ratesByPackage = TourPackageRate::whereIn('tour_package_id', $packages->pluck('id'))
                ->where('affiliate_id', $affiliate->id)
                ->where('scheduling_year', $todayYear)
                ->where('status', 'Active')
                ->whereNull('custom_date_id')
                ->get()
                ->groupBy('tour_package_id')
                ->map(fn ($group) => $group->keyBy('id'));

            // Resolve effective rates for today per package using same pattern as getTourRatesBySlot:
            // isCustomDateRateEnabled returns the active custom_date_id (or null), then query directly.
            $effectiveRatesByPackage = [];
            if (OctoService::hasCapability('pricing')) {
                foreach ($packages as $package) {
                    $customDateId = isCustomDateRateEnabled($today, $package->id, $affiliate->id);
                    $effective = TourPackageRate::where('tour_package_id', $package->id)
                        ->where('affiliate_id', $affiliate->id)
                        ->where('scheduling_year', $todayYear)
                        ->where('status', 'Active')
                        ->when($customDateId, fn ($q) => $q->where('custom_date_id', $customDateId), fn ($q) => $q->whereNull('custom_date_id'))
                        ->orderBy('orderby')
                        ->get()->keyBy('id');
                    if ($effective->isNotEmpty()) {
                        $effectiveRatesByPackage[$package->id] = $effective;
                    }
                }
            }

            // Batch-load all start times in one query — avoids N+1 per package
            $startTimesByPackage = OctoService::batchAvailabilityStartTimes(
                $packages->pluck('id')->toArray(),
                $affiliate->id
            );

            $photosByPackage = collect();
            $categories      = collect();

            if (OctoService::hasCapability('content')) {
                $photosByPackage = DB::table('tour_package_photos')
                    ->whereIn('tour_package_id', $packages->pluck('id'))
                    ->where('status', 'Active')
                    ->where('deleted_by', 0)
                    ->orderBy('placement')
                    ->get(['tour_package_id', 'path'])
                    ->groupBy('tour_package_id');

                $categoryIds = $packages->pluck('category_id')->filter()->unique()->toArray();
                $categories  = Category::whereIn('id', $categoryIds)->pluck('name', 'id');
            }

            $products = $packages->map(fn (TourPackage $package) =>
                $this->formatProduct(
                    $package,
                    $operator,
                    $country,
                    $ratesByPackage->get($package->id, collect()),
                    $startTimesByPackage[$package->id] ?? [],
                    $photosByPackage->get($package->id, collect())->toArray(),
                    $categories->get($package->category_id),
                    (float) ($affiliate->ota_affiliate_percentage ?? 0),
                    $affiliate->min_pax,
                    $affiliate->max_pax,
                    $effectiveRatesByPackage[$package->id] ?? null
                )
            );

            return response()->json($products);
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    /**
     * GET /products/{productId}
     */
    public function getProductById(string $productId): JsonResponse
    {
        try {
            $affiliate = $this->getAffiliateByReseller();
            if ($affiliate instanceof JsonResponse) return $affiliate;

            $assignedPackageIds = PackageAffiliateAssignment::where('affiliate_id', $affiliate->id)
                ->pluck('package_id');

            $package = TourPackage::whereIn('id', $assignedPackageIds)->active()->find($productId);

            if (!$package) {
                return OctoErrorResponse::invalidProductId($productId);
            }

            $operator = TourOperator::with('city.state.country')->first();
            $country  = $operator?->city?->state?->country;

            $today    = now($operator?->timezone ?? 'UTC')->toDateString();
            $todayYear = (int) substr($today, 0, 4);

            $rates = TourPackageRate::where('tour_package_id', $package->id)
                ->where('affiliate_id', $affiliate->id)
                ->where('scheduling_year', $todayYear)
                ->where('status', 'Active')
                ->whereNull('custom_date_id')
                ->get()
                ->keyBy('id');

            $effectiveRates = null;
            if (OctoService::hasCapability('pricing')) {
                $customDateId = isCustomDateRateEnabled($today, $package->id, $affiliate->id);
                $resolved = TourPackageRate::where('tour_package_id', $package->id)
                    ->where('affiliate_id', $affiliate->id)
                    ->where('scheduling_year', $todayYear)
                    ->where('status', 'Active')
                    ->when($customDateId, fn ($q) => $q->where('custom_date_id', $customDateId), fn ($q) => $q->whereNull('custom_date_id'))
                    ->orderBy('orderby')
                    ->get()->keyBy('id');
                $effectiveRates = $resolved->isNotEmpty() ? $resolved : null;
            }

            $startTimes = OctoService::getAvailabilityStartTimes($package->id, $affiliate->id);

            $photos       = [];
            $categoryName = null;
            $otaAffiliatePercentage = (float) ($affiliate->ota_affiliate_percentage ?? 0);

            if (OctoService::hasCapability('content')) {
                $photos = DB::table('tour_package_photos')
                    ->where('tour_package_id', $package->id)
                    ->where('status', 'Active')
                    ->where('deleted_by', 0)
                    ->orderBy('placement')
                    ->get(['path'])
                    ->toArray();

                $categoryName = $package->category_id
                    ? Category::find($package->category_id)?->name
                    : null;
            }

            return response()->json(
                $this->formatProduct($package, $operator, $country, $rates, $startTimes, $photos, $categoryName, $otaAffiliatePercentage, $affiliate->min_pax, $affiliate->max_pax, $effectiveRates)
            );
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    /**
     * POST /availability/calendar
     */
    public function getAvailabilityCalendar(): JsonResponse
    {
        try {
            $resolved = $this->resolveAvailabilityRequest();
            if ($resolved instanceof JsonResponse) return $resolved;

            [$affiliate, $package, $startDate, $endDate, $operator] = $resolved;

            $calendarCutoffHours = (int) ($package->ota_duration ?? 0);

            $calendar = OctoService::getCalendarAvailability(
                $package->id, $affiliate->id, $startDate, $endDate,
                $operator?->timezone ?? 'UTC',
                $calendarCutoffHours,
                $affiliate->max_pax // Affiliate-level max pax from customers table
            );

            if (OctoService::hasCapability('pricing')) {
                $country         = TourOperator::with('city.state.country')->first()?->city?->state?->country;
                $defaultCurrency = $country?->currency ?? 'USD';
                $currency        = request()->input('currency') ?? $defaultCurrency;
                $requestUnits    = request()->input('units', []);
                $otaPercentage   = (float) ($affiliate->ota_affiliate_percentage ?? 0);

                // Resolve per-date effective rates: custom rates take precedence over standard
                // rates for any date that falls inside a surcharge window.
                $pricingByDate = $this->resolvePricingByDate($package->id, $affiliate->id, $startDate, $endDate);

                $calendar = array_map(function ($entry) use ($pricingByDate, $currency, $otaPercentage, $requestUnits) {
                    $date           = $entry['localDate'];
                    $effectiveRates = $pricingByDate['by_date'][$date] ?? collect();

                    if ($effectiveRates->isEmpty()) {
                        return $entry;
                    }

                    $datePricing = $this->buildRatesPricing($effectiveRates, $currency, $otaPercentage);

                    if ($datePricing['pricingPer'] === 'UNIT') {
                        $unitPricingFrom = [];
                        foreach ($effectiveRates as $rateId => $rateRow) {
                            $priceEntry = $datePricing['perRateId'][(string) $rateId][0] ?? null;
                            if ($priceEntry !== null) {
                                $unitPricingFrom[] = array_merge(['unitId' => (string) $rateId], $priceEntry);
                            }
                        }
                        $entry['unitPricingFrom'] = $unitPricingFrom;
                    }

                    if ($datePricing['pricingPer'] === 'BOOKING') {
                        // Expose all group rate tiers so OTA can select the right tier by group size
                        $groupUnitPricingFrom = [];
                        foreach ($effectiveRates as $rateId => $rateRow) {
                            $priceEntry = $datePricing['perRateId'][(string) $rateId][0] ?? null;
                            if ($priceEntry !== null) {
                                $groupUnitPricingFrom[] = array_merge(['unitId' => (string) $rateId], $priceEntry);
                            }
                        }
                        $entry['unitPricingFrom'] = $groupUnitPricingFrom;

                        // If OTA specified a unit, return that tier's flat price; otherwise return minimum
                        $matchedPrice = null;
                        foreach ($requestUnits as $requestUnit) {
                            $unitId = $requestUnit['id'] ?? null;
                            if ($unitId && isset($datePricing['perRateId'][$unitId])) {
                                $matchedPrice = $datePricing['perRateId'][$unitId][0];
                                break;
                            }
                        }
                        $entry['pricingFrom'] = $matchedPrice ? [$matchedPrice] : $datePricing['pricingFrom'];
                    } elseif (!empty($requestUnits)) {
                        $totalNet        = $totalRetail = $totalOriginal = 0;
                        $mergedTaxes     = [];
                        $hasMatchingUnit = false;

                        foreach ($requestUnits as $requestUnit) {
                            $unitId   = $requestUnit['id'] ?? null;
                            $quantity = max(1, (int) ($requestUnit['quantity'] ?? 1));

                            if ($unitId && isset($datePricing['perRateId'][$unitId])) {
                                $hasMatchingUnit = true;
                                $unitPrice        = $datePricing['perRateId'][$unitId][0];
                                $totalNet        += $unitPrice['net'] * $quantity;
                                $totalRetail     += $unitPrice['retail'] * $quantity;
                                $totalOriginal   += $unitPrice['original'] * $quantity;

                                foreach ($unitPrice['includedTaxes'] as $tax) {
                                    $key = $tax['name'];
                                    if (!isset($mergedTaxes[$key])) {
                                        $mergedTaxes[$key] = ['name' => $tax['name'], 'retail' => 0, 'original' => 0, 'net' => 0];
                                    }
                                    $mergedTaxes[$key]['retail']   += $tax['retail'] * $quantity;
                                    $mergedTaxes[$key]['original'] += $tax['original'] * $quantity;
                                    $mergedTaxes[$key]['net']      += $tax['net'] * $quantity;
                                }
                            }
                        }

                        if ($hasMatchingUnit) {
                            $entry['pricingFrom'] = [[
                                'original'          => $totalOriginal,
                                'retail'            => $totalRetail,
                                'net'               => $totalNet,
                                'currency'          => $currency,
                                'currencyPrecision' => 2,
                                'includedTaxes'     => array_values($mergedTaxes),
                            ]];
                        } else {
                            $entry['pricingFrom'] = $datePricing['pricingFrom'];
                        }
                    } else {
                        $entry['pricingFrom'] = $datePricing['pricingFrom'];
                    }

                    return $entry;
                }, $calendar);
            }

            return response()->json($calendar);
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    /**
     * POST /availability
     */
    public function getAvailabilityCheck(): JsonResponse
    {
        try {
            $availabilityIds = request()->input('availabilityIds', []);
            $byIds           = !empty($availabilityIds);
            $hasDateRange    = request()->has('localDateStart') || request()->has('localDateEnd');

            if ($byIds && $hasDateRange) {
                return OctoErrorResponse::badRequest('availabilityIds and localDateStart/localDateEnd are mutually exclusive.');
            }

            $resolved = $this->resolveAvailabilityRequest(datesRequired: !$byIds);
            if ($resolved instanceof JsonResponse) return $resolved;

            [$affiliate, $package, $startDate, $endDate, $operator] = $resolved;

            $otaCutoffHours    = (int) ($package->ota_duration ?? 0);
            $unitPricingByDate = null; // populated by the date-range branch below

            if ($byIds) {
                $standardUnits = OctoService::hasCapability('availability')
                    ? TourPackageRate::where('tour_package_id', $package->id)
                        ->where('affiliate_id', $affiliate->id)
                        ->where('status', 'Active')
                        ->whereNull('custom_date_id')
                        ->get()
                        ->keyBy('id')
                    : null;

                $availabilities = OctoService::getAvailabilityCheckByIds($package->id, $affiliate->id, $availabilityIds, $operator, $otaCutoffHours, $standardUnits, $affiliate->min_pax, $affiliate->max_pax);
            } else {
                $unitPricingByDate = OctoService::hasCapability('pricing')
                    ? $this->resolvePricingByDate($package->id, $affiliate->id, $startDate, $endDate)
                    : null;

                $standardUnits = OctoService::hasCapability('availability')
                    ? TourPackageRate::where('tour_package_id', $package->id)
                        ->where('affiliate_id', $affiliate->id)
                        ->where('scheduling_year', (int) substr($startDate, 0, 4))
                        ->where('status', 'Active')
                        ->whereNull('custom_date_id')
                        ->get()
                        ->keyBy('id')
                    : null;

                $availabilities = OctoService::getAvailabilityCheck($package->id, $affiliate->id, $startDate, $endDate, $operator, $otaCutoffHours, $unitPricingByDate, $standardUnits, $affiliate->min_pax, $affiliate->max_pax);
            }

            if (OctoService::hasCapability('pricing')) {
                $country         = TourOperator::with('city.state.country')->first()?->city?->state?->country;
                $defaultCurrency = $country?->currency ?? 'USD';
                $currency        = request()->input('currency') ?? $defaultCurrency;
                $requestUnits    = request()->input('units', []);
                $otaPercentage   = (float) ($affiliate->ota_affiliate_percentage ?? 0);

                if ($unitPricingByDate !== null) {
                    // Date-range path — OctoService::getAvailabilityCheck set unitPricing per-slot
                    // using buildPricingObject (net=null, no OTA %). We replace it here with
                    // buildRatesPricing output which carries the correct net and OTA-adjusted retail.
                    $availabilities = array_map(function ($entry) use ($unitPricingByDate, $currency, $requestUnits, $otaPercentage) {
                        $date    = substr($entry['localDateTimeStart'], 0, 10);
                        $time    = substr($entry['openingHours'][0]['from'] ?? '00:00', 0, 5);
                        $slotKey = $date . '_' . $time;

                        $ratesForSlot = $unitPricingByDate['by_slot_time'][$slotKey]
                            ?? $unitPricingByDate['by_date'][$date]
                            ?? collect();

                        if ($ratesForSlot->isEmpty()) {
                            return $entry;
                        }

                        $slotPricing = $this->buildRatesPricing($ratesForSlot, $currency, $otaPercentage);

                        $unitPricing = [];
                        foreach ($ratesForSlot as $rateId => $rateRow) {
                            $priceEntry = $slotPricing['perRateId'][(string) $rateId][0] ?? null;
                            if ($priceEntry !== null) {
                                $unitPricing[] = array_merge(['unitId' => (string) $rateId], $priceEntry);
                            }
                        }
                        $entry['unitPricing'] = $unitPricing;

                        if (!empty($requestUnits)) {
                            $totalNet        = $totalRetail = $totalOriginal = 0;
                            $mergedTaxes     = [];
                            $hasMatchingUnit = false;

                            foreach ($requestUnits as $requestUnit) {
                                $unitId   = $requestUnit['id'] ?? null;
                                $quantity = max(1, (int) ($requestUnit['quantity'] ?? 1));

                                if ($unitId && isset($slotPricing['perRateId'][$unitId])) {
                                    $hasMatchingUnit = true;
                                    $unitPrice       = $slotPricing['perRateId'][$unitId][0];
                                    $isGroupUnit     = (int) ($ratesForSlot->get($unitId)?->is_group_rate ?? 0) === 1;

                                    // Group rate: flat per booking — no quantity multiplication
                                    $multiplier      = $isGroupUnit ? 1 : $quantity;

                                    $totalNet        += $unitPrice['net'] * $multiplier;
                                    $totalRetail     += $unitPrice['retail'] * $multiplier;
                                    $totalOriginal   += $unitPrice['original'] * $multiplier;

                                    foreach ($unitPrice['includedTaxes'] as $tax) {
                                        $key = $tax['name'];
                                        if (!isset($mergedTaxes[$key])) {
                                            $mergedTaxes[$key] = ['name' => $tax['name'], 'retail' => 0, 'original' => 0, 'net' => 0];
                                        }
                                        $mergedTaxes[$key]['retail']   += $tax['retail'] * $multiplier;
                                        $mergedTaxes[$key]['original'] += $tax['original'] * $multiplier;
                                        $mergedTaxes[$key]['net']      += $tax['net'] * $multiplier;
                                    }
                                }
                            }

                            if ($hasMatchingUnit) {
                                $entry['pricing'] = [
                                    'original'          => $totalOriginal,
                                    'retail'            => $totalRetail,
                                    'net'               => $totalNet,
                                    'currency'          => $currency,
                                    'currencyPrecision' => 2,
                                    'includedTaxes'     => array_values($mergedTaxes),
                                ];
                            } elseif (!empty($slotPricing['pricingFrom'])) {
                                $entry['pricing'] = $slotPricing['pricingFrom'][0];
                            }
                        } elseif (!empty($slotPricing['pricingFrom'])) {
                            $entry['pricing'] = $slotPricing['pricingFrom'][0];
                        }

                        return $entry;
                    }, $availabilities);
                } else {
                    // byIds path — unitPricing was not pre-populated, so set it here from
                    // standard rates (no date context available for custom-rate resolution).
                    $rates = TourPackageRate::where('tour_package_id', $package->id)
                        ->where('affiliate_id', $affiliate->id)
                        ->where('status', 'Active')
                        ->whereNull('custom_date_id')
                        ->get()
                        ->keyBy('id');

                    $pricing = $this->buildRatesPricing($rates, $currency, $otaPercentage);

                    $aggregatePricing = !empty($pricing['pricingFrom']) ? $pricing['pricingFrom'][0] : null;

                    if (!empty($requestUnits)) {
                        $totalNet        = 0;
                        $totalRetail     = 0;
                        $totalOriginal   = 0;
                        $mergedTaxes     = [];
                        $hasMatchingUnit = false;

                        foreach ($requestUnits as $requestUnit) {
                            $unitId   = $requestUnit['id'] ?? null;
                            $quantity = max(1, (int) ($requestUnit['quantity'] ?? 1));

                            if ($unitId && isset($pricing['perRateId'][$unitId])) {
                                $hasMatchingUnit = true;
                                $unitPrice       = $pricing['perRateId'][$unitId][0];
                                $isGroupUnit     = (int) ($rates->get($unitId)?->is_group_rate ?? 0) === 1;

                                // Group rate: flat per booking — no quantity multiplication
                                $multiplier      = $isGroupUnit ? 1 : $quantity;

                                $totalNet        += $unitPrice['net'] * $multiplier;
                                $totalRetail     += $unitPrice['retail'] * $multiplier;
                                $totalOriginal   += $unitPrice['original'] * $multiplier;

                                foreach ($unitPrice['includedTaxes'] as $tax) {
                                    $key = $tax['name'];
                                    if (!isset($mergedTaxes[$key])) {
                                        $mergedTaxes[$key] = ['name' => $tax['name'], 'retail' => 0, 'original' => 0, 'net' => 0];
                                    }
                                    $mergedTaxes[$key]['retail']   += $tax['retail'] * $multiplier;
                                    $mergedTaxes[$key]['original'] += $tax['original'] * $multiplier;
                                    $mergedTaxes[$key]['net']      += $tax['net'] * $multiplier;
                                }
                            }
                        }

                        if ($hasMatchingUnit) {
                            $aggregatePricing = [
                                'original'          => $totalOriginal,
                                'retail'            => $totalRetail,
                                'net'               => $totalNet,
                                'currency'          => $currency,
                                'currencyPrecision' => 2,
                                'includedTaxes'     => array_values($mergedTaxes),
                            ];
                        }
                    }

                    $availabilities = array_map(function ($entry) use ($pricing, $aggregatePricing) {
                        if ($aggregatePricing !== null) {
                            $entry['pricing'] = $aggregatePricing;
                        }
                        $entry['unitPricing'] = $pricing['unitPricingFrom'];
                        return $entry;
                    }, $availabilities);
                }
            }

            return response()->json($availabilities);
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    /**
     * Shared validation + lookup for availability endpoints.
     * Returns [affiliate, package, startDate, endDate, operator] on success, or a JsonResponse error.
     */
    private function resolveAvailabilityRequest(bool $datesRequired = true): array|JsonResponse
    {
        $affiliate = $this->getAffiliateByReseller();
        if ($affiliate instanceof JsonResponse) return $affiliate;

        $productId = request()->input('productId');
        $optionId  = request()->input('optionId');
        $startDate = request()->input('localDateStart');
        $endDate   = request()->input('localDateEnd');

        if (!$productId) {
            return OctoErrorResponse::invalidProductId('', 'productId is invalid or missing.');
        }

        if (!$optionId) {
            return OctoErrorResponse::invalidOptionId('', 'optionId is invalid or missing.');
        }

        if ($datesRequired) {
            if (!$startDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
                return OctoErrorResponse::badRequest('localDateStart must be a valid date in Y-m-d format.');
            }

            if (!$endDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
                return OctoErrorResponse::badRequest('localDateEnd must be a valid date in Y-m-d format.');
            }
        }

        $assignedPackageIds = PackageAffiliateAssignment::where('affiliate_id', $affiliate->id)
            ->pluck('package_id');

        $package = TourPackage::whereIn('id', $assignedPackageIds)->active()->find($productId);

        if (!$package) {
            return OctoErrorResponse::invalidProductId($productId);
        }

        if ($optionId !== 'default') {
            return OctoErrorResponse::invalidOptionId($optionId);
        }

        $operator = TourOperator::first();

        return [$affiliate, $package, $startDate, $endDate, $operator];
    }

    private function buildOperatorMedia($operator): array
    {
        $media = [];

        if (!empty($operator->logo)) {
            $s3Path = 'images/tour-operator/' . $operator->tenant_id . '/logo/' . $operator->logo;
            $media[] = OctoService::buildMediaObject(
                $this->s3Url($s3Path),
                'LOGO',
                'image/png',
                $operator->name,
                null,
                $operator->name
            );
        }

        return $media;
    }

    private function getAffiliateByReseller(): Customer|JsonResponse
    {
        $resellerName = request()->octo_reseller;

        // Look up by ota_id (the stable, immutable slug) rather than the display name,
        // so renaming an affiliate in the admin panel does not break the OCTO integration.
        $affiliate = Customer::isAffiliate()->where('ota_id', $resellerName)->where('is_ota', 1)->first();

        if (!$affiliate) {
            return OctoErrorResponse::unprocessableEntity("Reseller '{$resellerName}' is not registered as an OTA affiliate. Ensure the affiliate's OTA ID matches the platform key in the OCTO auth config.");
        }

        return $affiliate;
    }

    private function formatProduct(TourPackage $package, $operator, $country, $rates, array $startTimes, array $photos = [], ?string $categoryName = null, ?float $otaPercentage = null, ?int $affiliateMinPax = null, ?int $affiliateMaxPax = null, ?\Illuminate\Support\Collection $effectiveRates = null): array
    {
        $currency = $country?->currency ?? 'USD';
        // Use effective rates (may include custom-date overrides) for pricing amounts;
        // fall back to standard rates when no effective-rate override was resolved.
        $pricingSource = $effectiveRates ?? $rates;
        $pricing = OctoService::hasCapability('pricing')
            ? $this->buildRatesPricing($pricingSource, $currency, (float) ($otaPercentage ?? 0))
            : null;

        $isGroupPackage = (int) $package->is_group_rate_enabled === 1;

        $units = [];
        foreach ($pricingSource as $rateId => $rateRow) {
            $isGroupUnit = $isGroupPackage && (int) $rateRow->is_group_rate === 1;

            $unit = [
                'id'           => (string) $rateRow->id,
                'internalName' => $rateRow->rate_for,
                'reference'    => null,
                'type'         => OctoService::mapUnitType($rateRow->rate_for),
                'restrictions' => [
                    'minAge'        => (int) ($rateRow->age_from ?? 0),
                    'maxAge'        => (int) ($rateRow->age_to ?? 99),
                    'idRequired'    => false,
                    'minQuantity'   => $isGroupUnit
                        ? (($rateRow->min_group_size > 0) ? (int) $rateRow->min_group_size : null)
                        : (($rateRow->min_pax_allowed && $rateRow->min_pax_allowed > 0) ? (int) $rateRow->min_pax_allowed : null),
                    'maxQuantity'   => $isGroupUnit
                        ? (($rateRow->max_group_size > 0) ? (int) $rateRow->max_group_size : null)
                        : (($rateRow->max_pax_allowed && $rateRow->max_pax_allowed > 0) ? (int) $rateRow->max_pax_allowed : null),
                    'paxCount'      => $isGroupUnit ? max(1, (int) $rateRow->min_group_size) : 1,
                    'accompaniedBy' => [],
                ],
                'requiredContactFields' => OctoService::requiredUnitContactFields(),
            ];

            if (OctoService::hasCapability('content')) {
                $unit['title']            = $rateRow->rate_for;
                $unit['shortDescription'] = $rateRow->description ?? null;
            }

            if (OctoService::hasCapability('pricing')) {
                $currency            = currencyFormation(code: true) ?: 'USD';
                $unit['pricingFrom'] = [OctoService::buildPricingObject($rateRow, $currency)];
            }

            if ($pricing) {
                $unit['pricingFrom'] = $pricing['perRateId'][(string) $rateId] ?? [];
            }

            $units[] = $unit;
        }

        $cancellationDays = (int) ($operator?->cancellation_within_days ?? 1);

        $option = [
            'id'                          => 'default',
            'default'                     => true,
            'internalName'                => $package->name,
            'reference'                   => null,
            'availabilityLocalStartTimes' => $startTimes,
            'cancellationCutoff'          => $cancellationDays . ' day',
            'cancellationCutoffAmount'    => $cancellationDays,
            'cancellationCutoffUnit'      => 'day',
            'requiredContactFields'       => OctoService::requiredContactFields(),
            'restrictions'                => [
                'minUnits' => $isGroupPackage ? 1 : ($affiliateMinPax ?: 0),
                'maxUnits' => $isGroupPackage ? 1 : ($affiliateMaxPax ?: 0),
            ],
            'units' => $units,
        ];

        $product = [
            'id'                   => (string) $package->id,
            'internalName'         => $package->name,
            'reference'            => null,
            'locale'               => $country?->locale ?? 'en',
            'timeZone'             => $operator?->timezone ?? 'UTC',
            'allowFreesale'        => true,
            'instantConfirmation'  => true,
            'instantDelivery'      => true,
            'availabilityRequired' => true,
            'availabilityType'     => 'START_TIME',
            'deliveryFormats'      => ['QRCODE'],
            'deliveryMethods'      => ['VOUCHER'],
            'redemptionMethod'     => 'DIGITAL',
            'options'              => [$option],
        ];

        if (OctoService::hasCapability('content')) {
            $tenantId = (string) $operator->tenant_id;
            $content  = OctoService::buildProductContent($package, $photos, $tenantId, $categoryName, $operator?->name);
            $product  = array_merge($product, $content['product']);
            $product['options'][0] = array_merge($product['options'][0], $content['option']);
        }

        if ($pricing) {
            $product['defaultCurrency']     = $currency;
            $product['availableCurrencies'] = [$currency];
            $product['pricingPer']          = $pricing['pricingPer'];
        }

        return $product;
    }

    private function resolveCurrency(): string
    {
        $country = TourOperator::with('city.state.country')->first()?->city?->state?->country;
        return request()->input('currency') ?? $country?->currency ?? 'USD';
    }

    private function buildBookingPricingFromRates(\Illuminate\Support\Collection $rates, array $rateCountById, string $currency, float $otaPercentage): array
    {
        $totalNet    = 0.0;
        $totalRetail = 0.0;
        $mergedTaxes = [];

        $retailDivisor = $otaPercentage < 100 ? (1 - $otaPercentage / 100) : 1;

        foreach ($rateCountById as $rateId => $qty) {
            $rate = $rates->get($rateId);
            if (!$rate) continue;

            $isGroupRate = (int) $rate->is_group_rate === 1;
            $baseAmount  = (float) ($rate->rate ?? 0) + (float) ($rate->additional_charge ?? 0) + ($isGroupRate ? 0.0 : (float) ($rate->permit_fee ?? 0));
            $taxPer      = $isGroupRate ? 0.0 : $baseAmount * (float) ($rate->additional_tax_percentage ?? 0) / 100;
            $net         = $baseAmount + $taxPer;
            $retail      = $net / $retailDivisor;
            $taxNet      = (float) ($rate->additional_charge ?? 0);
            $taxRetail   = $taxNet / $retailDivisor;
            $multiplier  = $isGroupRate ? 1 : $qty;

            $totalNet    += $net * $multiplier;
            $totalRetail += $retail * $multiplier;

            if ($taxNet > 0) {
                if (!isset($mergedTaxes['Tax'])) {
                    $mergedTaxes['Tax'] = ['name' => 'Tax', 'retail' => 0.0, 'net' => 0.0];
                }
                $mergedTaxes['Tax']['retail'] += $taxRetail * $multiplier;
                $mergedTaxes['Tax']['net']    += $taxNet * $multiplier;
            }
        }

        return [
            'original'          => (int) round($totalRetail * 100),
            'retail'            => (int) round($totalRetail * 100),
            'net'               => (int) round($totalNet * 100),
            'currency'          => $currency,
            'currencyPrecision' => 2,
            'includedTaxes'     => array_values(array_map(fn ($t) => [
                'name'   => $t['name'],
                'retail' => (int) round($t['retail'] * 100),
                'net'    => (int) round($t['net'] * 100),
            ], $mergedTaxes)),
        ];
    }

    private function buildPricingForBooking(OctoBooking $octoBooking, string $currency, float $otaPercentage): ?array
    {
        $unitIds = array_column($octoBooking->unit_items ?? [], 'unitId');
        if (empty($unitIds)) return null;

        $rateCountById = array_count_values($unitIds);
        $affiliateId   = $octoBooking->affiliate_id;
        $packageId     = $octoBooking->tour_package_id;

        if ($octoBooking->status === 'CONFIRMED' && $octoBooking->tour_booking_id) {
            // A confirmed booking's price is a locked-in historical fact — source it from
            // what was actually charged (tour_bookings_transact) rather than a live rate-
            // sheet recompute, so it doesn't drift if rates are edited afterward. Re-keyed
            // via translateRateId as a defensive no-op safety net — OctoLegacyBookingBackfillService
            // already re-points legacy transact rows to their OCTO-affiliate rate id at
            // backfill time, so this is normally a same-id passthrough.
            $rates = collect();
            foreach (BookingTransact::where('tour_booking_id', $octoBooking->tour_booking_id)->get() as $transact) {
                try {
                    $keyRateId = OctoLegacyBookingBackfillService::translateRateId((int) $transact->tour_package_rate_id, $packageId, $affiliateId)->id;
                } catch (RuntimeException $exception) {
                    continue;
                }
                $rates[(string) $keyRateId] = $transact;
            }
        } else {
            // ON_HOLD — nothing charged yet, so quote from the live rate sheet.
            $rates = TourPackageRate::whereIn('id', array_keys($rateCountById))
                ->where('affiliate_id', $affiliateId)
                ->where('status', 'Active')
                ->get()
                ->keyBy('id');
        }

        return [
            'booking' => $this->buildBookingPricingFromRates($rates, $rateCountById, $currency, $otaPercentage),
            'perUnit' => $this->buildUnitPricingByRateId($rates, $currency, $otaPercentage),
        ];
    }

    private function buildUnitPricingByRateId(\Illuminate\Support\Collection $rates, string $currency, float $otaPercentage): array
    {
        $map = [];
        $retailDivisor = $otaPercentage < 100 ? (1 - $otaPercentage / 100) : 1;

        foreach ($rates as $rateId => $rate) {
            $isGroupRate = (int) $rate->is_group_rate === 1;
            $base        = (float) ($rate->rate ?? 0) + (float) ($rate->additional_charge ?? 0) + ($isGroupRate ? 0.0 : (float) ($rate->permit_fee ?? 0));
            $tax         = $isGroupRate ? 0.0 : $base * (float) ($rate->additional_tax_percentage ?? 0) / 100;
            $net         = $base + $tax;
            $retail    = $net / $retailDivisor;
            $taxNet    = (float) ($rate->additional_charge ?? 0);
            $taxRetail = $taxNet / $retailDivisor;

            $map[(string) $rateId] = [
                'original'          => (int) round($retail * 100),
                'retail'            => (int) round($retail * 100),
                'net'               => (int) round($net * 100),
                'currency'          => $currency,
                'currencyPrecision' => 2,
                'includedTaxes'     => $taxNet > 0 ? [[
                    'name'   => 'Tax',
                    'retail' => (int) round($taxRetail * 100),
                    'net'    => (int) round($taxNet * 100),
                ]] : [],
            ];
        }

        return $map;
    }

    private function buildRatesPricing(iterable $rates, string $currency, float $otaPercentage): array
    {
        $pricingPer      = collect($rates)->contains(fn ($r) => (int) $r->is_group_rate === 1) ? 'BOOKING' : 'UNIT';
        $unitPricingFrom  = [];
        $perRateId        = [];
        $minNet           = null;
        $minRetail        = null;
        $minIncludedTaxes = [];

        $retailDivisor = $otaPercentage < 100 ? (1 - $otaPercentage / 100) : 1;

        foreach ($rates as $rateId => $rateRow) {
            $isGroupRate   = (int) $rateRow->is_group_rate === 1;
            $baseAmount    = (float) ($rateRow->rate ?? 0) + (float) ($rateRow->additional_charge ?? 0) + ($isGroupRate ? 0.0 : (float) ($rateRow->permit_fee ?? 0));
            $per           = $isGroupRate ? 0.0 : $baseAmount * (float) ($rateRow->additional_tax_percentage ?? 0) / 100;
            $net           = $baseAmount + $per;
            $retail        = $net / $retailDivisor;
            $taxNet        = (float) ($rateRow->additional_charge ?? 0);
            $taxRetail     = $taxNet / $retailDivisor;
            $includedTaxes = $taxNet > 0 ? [[
                'name'     => 'Tax',
                'retail'   => (int) round($taxRetail * 100),
                'original' => (int) round($taxRetail * 100),
                'net'      => (int) round($taxNet * 100),
            ]] : [];

            $priceEntry = [
                'original'          => (int) round($retail * 100),
                'retail'            => (int) round($retail * 100),
                'net'               => (int) round($net * 100),
                'currency'          => $currency,
                'currencyPrecision' => 2,
                'includedTaxes'     => $includedTaxes,
            ];

            $unitPricingFrom[] = array_merge(['unitId' => (string) $rateId], $priceEntry);
            $perRateId[(string) $rateId] = [$priceEntry];

            if ($net > 0 && ($minNet === null || $net < $minNet)) {
                $minNet          = $net;
                $minRetail       = $retail;
                $minIncludedTaxes = $includedTaxes;
            }
        }

        $pricingFrom = $minNet !== null ? [[
            'original'          => (int) round($minRetail * 100),
            'retail'            => (int) round($minRetail * 100),
            'net'               => (int) round($minNet * 100),
            'currency'          => $currency,
            'currencyPrecision' => 2,
            'includedTaxes'     => $minIncludedTaxes ?? [],
        ]] : [];

        return [
            'pricingPer'      => $pricingPer,
            'pricingFrom'     => $pricingFrom,
            'unitPricingFrom' => $unitPricingFrom,
            'perRateId'       => $perRateId,
        ];
    }


    // ── Booking endpoints ─────────────────────────────────────────────────────
    /**
     * GET /bookings
     *
     * Lists bookings for the authenticated reseller filtered by resellerReference
     * or supplierReference. At least one filter is required per OCTO spec.
     */
    public function listBookings(): JsonResponse
    {
        try {
            $affiliate = $this->getAffiliateByReseller();
            if ($affiliate instanceof JsonResponse) return $affiliate;

            $resellerReference = request()->input('resellerReference');
            $supplierReference = request()->input('supplierReference');

            if (!$resellerReference && !$supplierReference) {
                return OctoErrorResponse::badRequest('At least one filter is required: resellerReference or supplierReference.');
            }

            $query = OctoBooking::where('affiliate_id', $affiliate->id);

            if ($supplierReference) {
                // supplierReference = 'OCTO-XXXXXX' — decode the numeric ID
                $numericId = (int) ltrim(str_replace('OCTO-', '', strtoupper($supplierReference)), '0');
                if ($numericId > 0) {
                    $query->where('id', $numericId);
                } else {
                    return response()->json([]);
                }
            }

            if ($resellerReference) {
                // resellerReference is stored inside the contact JSON field
                $query->whereJsonContains('contact->resellerReference', $resellerReference);
            }

            $bookings = $query->get();

            $currency   = $this->resolveCurrency();
            $otaPct     = (float) ($affiliate->ota_affiliate_percentage ?? 0);

            $results = $bookings->map(function (OctoBooking $booking) use ($currency, $otaPct) {
                if ($booking->status === 'CONFIRMED') {
                    $booking->load('tourBooking');
                }
                $pricingData = OctoService::hasCapability('pricing')
                    ? $this->buildPricingForBooking($booking, $currency, $otaPct)
                    : null;

                return OctoService::formatBookingResponse($booking, $pricingData['booking'] ?? null, $pricingData['perUnit'] ?? null);
            })->values()->all();

            return response()->json($results);
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    /**
     * POST /bookings
     * Creates an ON_HOLD reservation; seats are held until confirmed or expired.
     */
    public function createBooking(): JsonResponse
    {
        try {
            $affiliate = $this->getAffiliateByReseller();
            if ($affiliate instanceof JsonResponse) return $affiliate;

            $clientUuid        = request()->input('uuid');
            $productId         = request()->input('productId');
            $optionId          = request()->input('optionId');
            $availabilityId    = request()->input('availabilityId');
            $unitItems         = request()->input('unitItems', []);
            $notes             = request()->input('notes');
            $expirationMinutes = max(1, (int) (request()->input('expirationMinutes') ?? 30));

            if (!$productId) {
                return OctoErrorResponse::invalidProductId('', 'A product must be selected to create a booking.');
            }

            if (!$optionId) {
                return OctoErrorResponse::invalidOptionId('', 'optionId is required.');
            }

            if ($optionId !== 'default') {
                return OctoErrorResponse::invalidOptionId($optionId);
            }

            if (!$availabilityId) {
                return OctoErrorResponse::invalidAvailabilityId('', 'A date and time slot must be selected to create a booking.');
            }

            if (empty($unitItems) || !is_array($unitItems)) {
                return OctoErrorResponse::unprocessableEntity('At least one guest must be added to create a booking.');
            }

            // A client-supplied uuid is the reseller's idempotency key, so it has to be a
            // real UUID: the column is globally unique, and short free-form keys ('order-1')
            // would collide across resellers. Only the supplied value is checked — an
            // omitted uuid is generated below and is valid by construction.
            if ($clientUuid !== null && $clientUuid !== '' && !(is_string($clientUuid) && Str::isUuid($clientUuid))) {
                return OctoErrorResponse::badRequest('The supplied booking uuid must be a valid RFC 4122 UUID.');
            }

            $uuid = $clientUuid ?: (string) Str::uuid();

            // Counted once here and reused for both the idempotency fingerprint and the
            // rate validation below, so the two can never disagree about what was asked for.
            $unitIds       = array_column($unitItems, 'unitId');
            $rateCountById = array_count_values(array_map('strval', $unitIds));

            $fingerprint = self::bookingIdempotencyFingerprint($productId, $availabilityId, $unitIds);

            // Idempotency — a replay of a known uuid resolves to the original booking,
            // and a uuid that describes a different booking is rejected as a conflict.
            $idempotent = $this->resolveIdempotentBooking($uuid, $affiliate, $fingerprint);
            if ($idempotent) {
                return $idempotent;
            }

            $assignedPackageIds = PackageAffiliateAssignment::where('affiliate_id', $affiliate->id)->pluck('package_id');
            $package = TourPackage::whereIn('id', $assignedPackageIds)->active()->find($productId);

            if (!$package) {
                return OctoErrorResponse::invalidProductId((string) $productId);
            }

            $slot = DB::table('tour_slot_times')->where('id', $availabilityId)->first();

            if (!$slot) {
                return OctoErrorResponse::invalidAvailabilityId((string) $availabilityId);
            }

            if (in_array($slot->bookable_status, ['Closed', 'Only by Phone'], true)) {
                return OctoErrorResponse::unprocessableEntity('The selected time slot is not available for booking.');
            }

            $operator = TourOperator::first();
            $timezone = $operator?->timezone ?? 'UTC';
            $slotStart = Carbon::createFromFormat('Y-m-d H:i:s', $slot->date . ' ' . $slot->time_from, $timezone);

            if ($slotStart->lte(Carbon::now($timezone))) {
                return OctoErrorResponse::unprocessableEntity('The selected time slot is in the past.');
            }

            $otaCutoffHours = (int) ($package->ota_duration ?? 0);
            if ($otaCutoffHours > 0 && $slotStart->lt(Carbon::now($timezone)->addHours($otaCutoffHours))) {
                return OctoErrorResponse::unprocessableEntity('The selected time slot is within the OTA booking cutoff window and cannot be reserved.');
            }

            // Validate the effective rate IDs supplied by the client (may be custom rate IDs on
            // surcharge dates or standard rate IDs otherwise). No custom_date_id filter — both
            // are valid unit identifiers, and pricing is already resolved in the rate row itself.
            $rates = TourPackageRate::whereIn('id', array_keys($rateCountById))
                ->where('tour_package_id', $package->id)
                ->where('affiliate_id', $affiliate->id)
                ->where('status', 'Active')
                ->get()
                ->keyBy('id');

            $tourists = 0;
            $infants  = 0;
            foreach ($rateCountById as $rateId => $qty) {
                $rate = $rates->get($rateId);
                if (!$rate) {
                    return OctoErrorResponse::invalidUnitId((string) $rateId, "Unit ID '{$rateId}' is not valid for this product.");
                }
                $rate->is_infant_rate ? ($infants += $qty) : ($tourists += $qty);
            }

            $isGroupPackage = (int) $package->is_group_rate_enabled === 1;

            if ($isGroupPackage) {
                // Group packages: validate group size against the selected tier's min/max group size
                foreach ($rateCountById as $rateId => $qty) {
                    $rate = $rates->get($rateId);
                    if (!$rate || (int) $rate->is_group_rate !== 1) continue;
                    $minGroup = (int) ($rate->min_group_size ?? 0);
                    $maxGroup = (int) ($rate->max_group_size ?? 0);
                    if ($minGroup > 0 && $qty < $minGroup) {
                        return OctoErrorResponse::unprocessableEntity("A minimum of {$minGroup} guests is required for this group rate.");
                    }
                    if ($maxGroup > 0 && $qty > $maxGroup) {
                        return OctoErrorResponse::unprocessableEntity("A maximum of {$maxGroup} guests is allowed for this group rate.");
                    }
                }
            } else {
                // Per-person packages: enforce affiliate-level min/max pax per booking
                if ($affiliate->min_pax && $tourists < $affiliate->min_pax) {
                    return OctoErrorResponse::unprocessableEntity(
                        "A minimum of {$affiliate->min_pax} pax is required per booking."
                    );
                }
                if ($affiliate->max_pax && $tourists > $affiliate->max_pax) {
                    return OctoErrorResponse::unprocessableEntity(
                        "A maximum of {$affiliate->max_pax} pax is allowed per booking."
                    );
                }
            }

            // Seat availability check for this affiliate
            $affiliateLimit = DB::table('affiliate_slot_times as ast')
                ->join('tour_slot_times_affiliate as tsta', 'tsta.id', '=', 'ast.tour_slot_time_id')
                ->where('tsta.tour_package_id', $package->id)
                ->where('tsta.time_from', $slot->time_from)
                ->where('tsta.status', 'Active')
                ->where('tsta.deleted_by', 0)
                ->where('ast.affiliate_id', $affiliate->id)
                ->where('ast.deleted_by', 0)
                ->value('ast.seat_limit');

            // null = no row in affiliate_slot_times; 0 = row exists but zero seats allocated
            if ($affiliateLimit === null || (int) $affiliateLimit === 0) {
                return OctoErrorResponse::unprocessableEntity('No seat allocation found for this time slot.');
            }

            $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            $formattedItems = array_map(function ($item) use ($chars) {
                $supplierRef = '';
                for ($i = 0; $i < 6; $i++) {
                    $supplierRef .= $chars[random_int(0, strlen($chars) - 1)];
                }
                return [
                    'uuid'              => (string) Str::uuid(),
                    'resellerReference' => (string) ($item['uuid'] ?? ''),
                    'supplierReference' => $supplierRef,
                    'unitId'            => (string) ($item['unitId'] ?? ''),
                    'contact'           => $item['contact'] ?? null,
                ];
            }, $unitItems);

            // Lock the affiliate seat row and re-verify inside a transaction to prevent
            // concurrent requests from over-reserving the last available seats.
            $reserve = function () use ($slot, $package, $affiliate, $affiliateLimit, $uuid, $tourists, $infants, $formattedItems, $notes, $expirationMinutes) {
                // Lock the slot itself first so this serializes against ANY concurrent
                // writer for this slot (e.g. a Bokun reservation, or a different affiliate's
                // OCTO hold) — the affiliate-scoped lock below only serializes same-affiliate
                // requests against each other and wouldn't block a different affiliate/channel
                // racing for the same global seat pool.
                TimeSlotService::lockSlotForUpdate((int) $slot->id);

                if ((int) $affiliateLimit > 0) {
                    // Acquire a row-level lock on the affiliate's seat allocation for this slot.
                    DB::table('affiliate_slot_times as ast')
                        ->join('tour_slot_times_affiliate as tsta', 'tsta.id', '=', 'ast.tour_slot_time_id')
                        ->where('tsta.tour_package_id', $package->id)
                        ->where('tsta.time_from', $slot->time_from)
                        ->where('ast.affiliate_id', $affiliate->id)
                        ->lockForUpdate()
                        ->first();

                    $remaining = OctoService::resolveRemainingSeats(
                        $slot->id,
                        $affiliate->id,
                        (int) $affiliateLimit,
                        (int) $slot->time_slot_seats,
                        $package->id,
                    );

                    if ($tourists > $remaining) {
                        return null;
                    }
                }

                return OctoBooking::create([
                    'uuid'            => $uuid,
                    'affiliate_id'    => $affiliate->id,
                    'tour_slot_id'    => $slot->id,
                    'tour_package_id' => $package->id,
                    'status'          => 'ON_HOLD',
                    'tourists'        => $tourists,
                    'infants'         => $infants,
                    'unit_items'      => $formattedItems,
                    'contact'         => null,
                    'notes'           => $notes,
                    'expire_at'       => now()->addMinutes($expirationMinutes),
                ]);
            };

            try {
                $octoBooking = DB::transaction($reserve);
            } catch (QueryException $queryException) {
                if (!self::isDuplicateKeyViolation($queryException)) {
                    throw $queryException;
                }

                // A concurrent request carrying the same uuid committed first, so this one
                // lost the race on the unique index. That is precisely the retry the key
                // exists for: resolve it against the winning booking instead of erroring.
                $raced = $this->resolveIdempotentBooking($uuid, $affiliate, $fingerprint);
                if ($raced) {
                    return $raced;
                }

                Log::error($queryException);
                return OctoErrorResponse::internalServerError();
            }

            if ($octoBooking === null) {
                return OctoErrorResponse::unprocessableEntity('Not enough seats available for the requested number of guests.');
            }

            $pricingData = null;
            if (OctoService::hasCapability('pricing')) {
                $holdCurrency = $this->resolveCurrency();
                $holdOtaPct   = (float) ($affiliate->ota_affiliate_percentage ?? 0);
                $pricingData  = [
                    'booking' => $this->buildBookingPricingFromRates($rates, $rateCountById, $holdCurrency, $holdOtaPct),
                    'perUnit' => $this->buildUnitPricingByRateId($rates, $holdCurrency, $holdOtaPct),
                ];
            }

            return response()->json(OctoService::formatBookingResponse($octoBooking, $pricingData['booking'] ?? null, $pricingData['perUnit'] ?? null));
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    /**
     * Stable fingerprint of the fields that define what a reservation actually reserves.
     * Used to tell a genuine retry (same uuid, same booking) apart from a reused uuid
     * that describes something different.
     *
     * Unit order is not significant, so units are counted rather than listed. Notes and
     * expirationMinutes are deliberately excluded — they do not change what is held.
     *
     * @param  array<int, mixed>  $unitIds
     */
    protected static function bookingIdempotencyFingerprint(mixed $productId, mixed $availabilityId, array $unitIds): string
    {
        $counts = array_count_values(array_map('strval', $unitIds));
        ksort($counts);

        return md5(json_encode([
            'productId'      => (string) $productId,
            'availabilityId' => (string) $availabilityId,
            'units'          => $counts,
        ]));
    }

    /**
     * Resolves a client-supplied booking uuid that already exists.
     *
     * Returns the original booking for a genuine retry, a conflict for a uuid that
     * belongs to someone else or describes a different booking, or null when the uuid
     * is unused and the caller should go on to create the reservation.
     *
     * The lookup is intentionally unscoped: the uuid column is globally unique, so a
     * uuid held by another reseller must be reported as a conflict rather than either
     * returning that reseller's booking or failing later on the unique index.
     */
    protected function resolveIdempotentBooking(string $uuid, Customer $affiliate, string $fingerprint): ?JsonResponse
    {
        $existing = OctoBooking::where('uuid', $uuid)->first();
        if (!$existing) {
            return null;
        }

        if ((int) $existing->affiliate_id !== (int) $affiliate->id) {
            return OctoErrorResponse::bookingUuidConflict($uuid);
        }

        $existingFingerprint = self::bookingIdempotencyFingerprint(
            $existing->tour_package_id,
            $existing->tour_slot_id,
            array_column($existing->unit_items ?? [], 'unitId')
        );

        if ($existingFingerprint !== $fingerprint) {
            return OctoErrorResponse::bookingUuidConflict(
                $uuid,
                'This uuid was already used for a booking with different product, availability or unit details. Use a new uuid, or repeat the original request unchanged.'
            );
        }

        if ($existing->status === 'CONFIRMED') {
            $existing->load('tourBooking');
        }

        $pricingData = OctoService::hasCapability('pricing')
            ? $this->buildPricingForBooking($existing, $this->resolveCurrency(), (float) ($affiliate->ota_affiliate_percentage ?? 0))
            : null;

        return response()->json(OctoService::formatBookingResponse($existing, $pricingData['booking'] ?? null, $pricingData['perUnit'] ?? null));
    }

    /** True when a QueryException is a MySQL duplicate-entry (ER_DUP_ENTRY) violation. */
    protected static function isDuplicateKeyViolation(QueryException $exception): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }

    /**
     * POST /bookings/{uuid}/confirm
     * Confirms an ON_HOLD booking and creates the tour_bookings_master record.
     */
    public function confirmBooking(string $uuid): JsonResponse
    {
        try {
            $affiliate = $this->getAffiliateByReseller();
            if ($affiliate instanceof JsonResponse) return $affiliate;

            $octoBooking = OctoBooking::where('uuid', $uuid)->where('affiliate_id', $affiliate->id)->first();

            if (!$octoBooking) {
                return OctoErrorResponse::invalidBookingUuid($uuid);
            }

            // Idempotent — already confirmed
            if ($octoBooking->status === 'CONFIRMED') {
                $octoBooking = $octoBooking->load('tourBooking');
                $pricingData = OctoService::hasCapability('pricing')
                    ? $this->buildPricingForBooking($octoBooking, $this->resolveCurrency(), (float) ($affiliate->ota_affiliate_percentage ?? 0))
                    : null;
                return response()->json(OctoService::formatBookingResponse($octoBooking, $pricingData['booking'] ?? null, $pricingData['perUnit'] ?? null));
            }

            if ($octoBooking->status === 'CANCELLED') {
                return OctoErrorResponse::bookingAlreadyCancelled($uuid);
            }

            if ($octoBooking->status !== 'ON_HOLD') {
                return OctoErrorResponse::unprocessableEntity("Booking cannot be confirmed — current status is {$octoBooking->status}.");
            }

            if ($octoBooking->isExpired()) {
                $octoBooking->update(['status' => 'EXPIRED']);
                $this->dispatchBookingNotifications($octoBooking->refresh());
                return OctoErrorResponse::bookingExpired($uuid);
            }

            $contact           = request()->input('contact') ?? [];
            $firstName         = $contact['firstName']    ?? null;
            $lastName          = $contact['lastName']     ?? null;
            $email             = $contact['emailAddress'] ?? null;
            $phone             = $contact['phoneNumber']  ?? null;
            $resellerReference = request()->input('resellerReference');

            $missingFields = array_filter([
                !$firstName ? 'contact.firstName'    : null,
                !$lastName  ? 'contact.lastName'     : null,
                !$email     ? 'contact.emailAddress' : null,
            ]);

            if (!empty($missingFields)) {
                return OctoErrorResponse::badRequest('Missing required contact field(s): ' . implode(', ', $missingFields) . '.');
            }

            $requestUnitItems = request()->input('unitItems', []);
            if (!empty($requestUnitItems) && is_array($requestUnitItems)) {
                $requestedUnitIds = array_column($requestUnitItems, 'unitId');
                $validUnitIds     = TourPackageRate::whereIn('id', $requestedUnitIds)
                    ->where('tour_package_id', $octoBooking->tour_package_id)
                    ->where('affiliate_id', $octoBooking->affiliate_id)
                    ->where('status', 'Active')
                    ->pluck('id')
                    ->map(fn ($id) => (string) $id)
                    ->toArray();

                foreach ($requestedUnitIds as $unitId) {
                    if (!in_array((string) $unitId, $validUnitIds, true)) {
                        return OctoErrorResponse::invalidUnitId((string) $unitId);
                    }
                }

                $chars        = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
                $updatedItems = array_map(function ($item) use ($chars) {
                    $ref = '';
                    for ($i = 0; $i < 6; $i++) {
                        $ref .= $chars[random_int(0, strlen($chars) - 1)];
                    }
                    return [
                        'uuid'              => (string) Str::uuid(),
                        'resellerReference' => (string) ($item['uuid'] ?? ''),
                        'supplierReference' => $ref,
                        'unitId'            => (string) ($item['unitId'] ?? ''),
                        'contact'           => $item['contact'] ?? null,
                    ];
                }, $requestUnitItems);

                $infantRateIds = TourPackageRate::where('affiliate_id', $octoBooking->affiliate_id)
                    ->where('tour_package_id', $octoBooking->tour_package_id)
                    ->where('is_infant_rate', 1)
                    ->pluck('id')
                    ->map(fn ($id) => (string) $id)
                    ->toArray();

                $newTourists = 0;
                $newInfants  = 0;
                foreach ($updatedItems as $item) {
                    in_array($item['unitId'], $infantRateIds, true) ? $newInfants++ : $newTourists++;
                }

                $octoBooking->update([
                    'unit_items' => $updatedItems,
                    'tourists'   => $newTourists,
                    'infants'    => $newInfants,
                ]);
                $octoBooking->refresh();
            }

            $slot = DB::table('tour_slot_times')->where('id', $octoBooking->tour_slot_id)->first();
            if (!$slot) {
                return OctoErrorResponse::unprocessableEntity('The reserved time slot no longer exists.');
            }

            $packageId = $octoBooking->tour_package_id;
            $package   = TourPackage::find($packageId);

            $affiliate = Customer::find($octoBooking->affiliate_id);

            // Use ota_id (stable slug) to resolve the canonical title from config/constants.php otas aliases.
            // This keeps request_from consistent even if the affiliate's display name is later renamed.
            $ota          = BokunUtils::initPrefix($affiliate?->ota_id ?? $affiliate?->name ?? 'octo');
            $resellerName = $ota['title'] ?? $affiliate?->name ?? 'Octo';
            $requestFrom  = strtoupper($resellerName);

            $otaPrefix           = $ota['prefix'] ?? 'OCTOTEST';
            $otaReferenceBooking = isset($resellerReference)
                ? $otaPrefix . '-' . $resellerReference
                : 'OCTOTEST-' . $uuid;

            $unitIds       = array_column($octoBooking->unit_items, 'unitId');
            $rateCountById = array_count_values($unitIds);

            $slotDate = $slot->date;
            $slotYear = Carbon::parse($slotDate)->year;

            $createdByWithName = BookingService::createdUserInfo(false, null, $affiliate->id, 'Affiliate');

            // The booking's unitId values are already the effective rate IDs (custom rate IDs on
            // surcharge dates, standard rate IDs otherwise) — load them directly, no re-resolution.
            $rates = TourPackageRate::whereIn('id', array_keys($rateCountById))
                ->where('tour_package_id', $packageId)
                ->where('affiliate_id', $octoBooking->affiliate_id)
                ->where('scheduling_year', $slotYear)
                ->where('status', 'Active')
                ->get()
                ->keyBy('id');

            DB::beginTransaction();

            $isGroupBooking = $rates->contains(fn ($r) => (int) $r->is_group_rate === 1);

            $tourBooking = TourBooking::create([
                'ota_reference_booking'    => $otaReferenceBooking,
                'tour_package_id'          => $packageId,
                'tour_slot_id'             => $slot->id,
                'customer_id'              => $affiliate->id,
                'name'                     => trim($firstName . ' ' . $lastName),
                'email'                    => $email,
                'phone_number'             => $phone ?? '',
                'tourists'                 => $octoBooking->tourists,
                'infants'                  => $octoBooking->infants,
                'comments'                 => $octoBooking->notes ?? '',
                'note'                     => '',
                'mask_required'            => 'Yes',
                'total'                    => 0,
                'booking_total'            => 0,
                'service_commission'       => 0,
                'liability_waiver_signing' => '',
                'status'                   => 'Booked',
                'is_group_booking'         => $isGroupBooking ? 1 : 0,
                'request_from'             => $resellerName,
                'created_by'               => $affiliate->id,
                'created_by_user_type'     => 'Affiliate',
            ]);

            // Store history of comments/notes added by Customer
            if (!empty($octoBooking->notes)) {
                DB::table('tour_booking_history')
                    ->insert([
                        'tour_booking_id'        => $tourBooking->id,
                        'operation'              => 'Comments',
                        'details'                => $octoBooking->notes,
                        'operation_at'           => mysqlDT(),
                        'operation_by'           => $affiliate->id,
                        'operation_by_user_type' => 'Affiliate',
                        'updated_at'             => now(),
                        'created_at'             => now(),
                    ]);
            }

            $tbTransact       = [];
            $bookingTotal     = 0.0;
            $addedPeoplesText = [];

            foreach ($rateCountById as $rateId => $qty) {
                $rate = $rates->get($rateId);
                if (!$rate) continue;

                $isGroupRate = (int) $rate->is_group_rate === 1;
                $multiplier  = $isGroupRate ? 1 : $qty;
                $lineTotal   = round(
                    ($rate->rate + $rate->additional_charge + ($isGroupRate ? 0 : $rate->permit_fee)) *
                    (1 + (float) $rate->processing_charge_percentage / 100) *
                    $multiplier,
                    2
                );

                $bookingTotal      += $lineTotal;
                $addedPeoplesText[] = $rate->rate_for . ' person ' . $qty;

                $tbTransact[] = [
                    'tour_booking_id'              => $tourBooking->id,
                    'tour_package_rate_id'         => $rate->id,
                    'tourists'                     => $rate->is_infant_rate ? 0 : $qty,
                    'infants'                      => $rate->is_infant_rate ? $qty : 0,
                    'is_infant_rate'               => $rate->is_infant_rate,
                    'is_group_rate'                => $isGroupRate ? 1 : 0,
                    'additional_tax_percentage'    => $isGroupRate ? 0 : ($rate->additional_tax_percentage ?? 0),
                    'processing_charge_percentage' => $rate->processing_charge_percentage ?? 0,
                    'nat_fees_percentage'          => $package->service_commission_percentage ?? 0,
                    'stripe_fees_percentage'       => $package->stripe_commission_percentage ?? 0,
                    'additional_charge'            => $rate->additional_charge ?? 0,
                    'permit_fee'                   => $isGroupRate ? 0 : ($rate->permit_fee ?? 0),
                    'rate'                         => $rate->rate ?? 0,
                    'total'                        => $lineTotal,
                    'check_in_status'              => '',
                    'deleted_by'                   => 0,
                    'created_by'                   => $affiliate->id,
                    'created_by_user_type'         => 'Affiliate',
                    'created_at'                   => now(),
                    'updated_at'                   => now(),
                ];
            }

            if (!empty($tbTransact)) {
                DB::table('tour_bookings_transact')->insert($tbTransact);
            }

            $tourBooking->update(['total' => $bookingTotal, 'booking_total' => $bookingTotal]);

            $transactionId = DB::table('transactions')->insertGetId([
                'tour_booking_id'       => $tourBooking->id,
                'amount'                => $bookingTotal,
                'fee_amount'            => 0,
                'via'                   => $resellerName,
                'transaction_number'    => null,
                'payment_type'          => 0,
                'response_init'         => '',
                'response_end'          => '',
                'response_end_datetime' => now(),
                'status'                => 'Completed',
                'visitor'               => request()->ip(),
                'request_from'          => $requestFrom,
                'created_by'            => $affiliate->id,
                'created_by_user_type'  => 'Affiliate',
                'created_at'            => now(),
            ]);

            $totalPax = $octoBooking->tourists + $octoBooking->infants;

            DB::table('tour_booking_history')->insert([
                'tour_booking_id'        => $tourBooking->id,
                'tourists'               => $totalPax,
                'operation'              => 'Added',
                'details'                => json_encode([
                    'sub_operation'        => 'Tourists Added',
                    'discount2_value'      => 0,
                    'added'                => $addedPeoplesText,
                    'newTourists'          => $totalPax,
                    'created_by_with_name' => $resellerName,
                    'discount_percentage'  => 0,
                    'discount_promo_code'  => '',
                ], JSON_NUMERIC_CHECK),
                'operation_at'           => mysqlDT(),
                'operation_by'           => $affiliate->id,
                'operation_by_user_type' => 'Affiliate',
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);

            DB::table('tour_booking_history')->insert([
                'tour_booking_id'         => $tourBooking->id,
                'transaction_id'          => $transactionId,
                'tourists'                => $totalPax,
                'operation'               => 'Payment',
                'ticket_cost'             => $bookingTotal,
                'platform_fees'           => 0,
                'payment_type'            => $resellerName,
                'payment_method'          => 'Full Payment',
                'is_processed'            => 1,
                'details'                 => json_encode([
                    'amount'               => $bookingTotal,
                    'payment_method'       => 'Full Payment',
                    'payment_type'         => $resellerName,
                    'created_by_with_name' => $createdByWithName,
                ]),
                'operation_at'            => mysqlDT(),
                'operation_by'            => $affiliate->id,
                'operation_by_user_type'  => 'Affiliate',
                'created_at'              => now(),
                'updated_at'              => now(),
            ]);

            if ($octoBooking->tourists > 0) {
                TimeSlotAvailabilityService::updateSlotSummary($slot->id, $octoBooking->tourists);
                $this->dispatchAvailabilityNotifications($octoBooking->affiliate_id, (string) $octoBooking->tour_package_id, $slot->date);
            }

            $octoBooking->update([
                'status'          => 'CONFIRMED',
                'tour_booking_id' => $tourBooking->id,
                'contact'         => array_merge($contact, ['resellerReference' => $resellerReference ?? '']),
                'confirmed_at'    => now(),
            ]);

            DB::commit();

            $octoBooking = $octoBooking->refresh()->load('tourBooking');
            $this->dispatchBookingNotifications($octoBooking);
            $pricingData = OctoService::hasCapability('pricing')
                ? $this->buildPricingForBooking($octoBooking, $this->resolveCurrency(), (float) ($affiliate->ota_affiliate_percentage ?? 0))
                : null;
            return response()->json(OctoService::formatBookingResponse($octoBooking, $pricingData['booking'] ?? null, $pricingData['perUnit'] ?? null));
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    /**
     * PATCH /bookings/{uuid}
     *
     * Updates an ON_HOLD or CONFIRMED booking.
     *
     * ON_HOLD   → unitItems, availabilityId, expirationMinutes, notes, contact
     * CONFIRMED → unitItems, availabilityId, notes, contact, resellerReference
     *             (structural changes blocked once within the OTA booking cutoff window)
     */
    public function updateBooking(string $uuid): JsonResponse
    {
        try {
            $affiliate = $this->getAffiliateByReseller();
            if ($affiliate instanceof JsonResponse) return $affiliate;

            try {
                $octoBooking = OctoLegacyBookingBackfillService::resolve($uuid, $affiliate);
            } catch (RuntimeException $exception) {
                return OctoErrorResponse::unprocessableEntity($exception->getMessage());
            }

            if (!$octoBooking) {
                return OctoErrorResponse::invalidBookingUuid($uuid);
            }

            if ($octoBooking->status === 'CANCELLED') {
                return OctoErrorResponse::bookingAlreadyCancelled($uuid);
            }

            if ($octoBooking->isExpired()) {
                $octoBooking->update(['status' => 'EXPIRED']);
                $this->dispatchBookingNotifications($octoBooking->refresh());
                return OctoErrorResponse::bookingExpired($uuid);
            }

            if (!in_array($octoBooking->status, ['ON_HOLD', 'CONFIRMED'], true)) {
                return OctoErrorResponse::unprocessableEntity("Booking cannot be updated — current status is {$octoBooking->status}.");
            }

            // OTA cutoff guard — structural changes (pax or slot) are blocked once the tour
            // is within its cancellation window regardless of booking status.
            $isStructuralChange = request()->has('unitItems') || request()->has('availabilityId');
            if ($isStructuralChange) {
                $operator         = TourOperator::first();
                $timezone         = $operator?->timezone ?? 'UTC';
                $cutoffHours      = (int) ($octoBooking->tourPackage?->ota_duration ?? 0);
                $cancellationDays = (int) ($operator?->cancellation_within_days ?? 1);
                $currentSlot      = DB::table('tour_slot_times')->where('id', $octoBooking->tour_slot_id)->first();

                if ($currentSlot) {
                    $slotStart = Carbon::createFromFormat('Y-m-d H:i:s', $currentSlot->date . ' ' . $currentSlot->time_from, $timezone);
                    $cutoff    = $cutoffHours > 0
                        ? $slotStart->copy()->subHours($cutoffHours)
                        : $slotStart->copy()->subDays($cancellationDays);

                    if (Carbon::now($timezone)->gte($cutoff)) {
                        return OctoErrorResponse::unprocessableEntity('People and slot changes are not allowed within the OTA booking cutoff window.');
                    }
                }
            }

            $updates = [];

            // --- Fields allowed on both ON_HOLD and CONFIRMED ---
            if (request()->has('notes')) {
                $updates['notes'] = request()->input('notes');
            }

            if ($this->hasEffectiveContactUpdate($octoBooking)) {
                $updates['contact'] = $this->resolveEffectiveContactForUpdate($octoBooking);
            }

            // For CONFIRMED bookings propagate all metadata changes to tour_bookings_master
            // so the internal backend record stays in sync with what the OTA sent.
            if ($octoBooking->status === 'CONFIRMED' && $octoBooking->tour_booking_id) {
                $masterUpdates = [];

                $resellerReference = request()->input('resellerReference');
                if ($resellerReference !== null) {
                    $ota       = BokunUtils::initPrefix($affiliate?->ota_id ?? $affiliate?->name ?? 'octo');
                    $otaPrefix = $ota['prefix'] ?? 'OCTOTEST';
                    $masterUpdates['ota_reference_booking'] = $otaPrefix . '-' . $resellerReference;
                }

                if ($this->hasEffectiveContactUpdate($octoBooking)) {
                    $contact = $this->resolveEffectiveContactForUpdate($octoBooking) ?? [];
                    // Normalize firstName+lastName → fullName for the master update field map
                    if (!isset($contact['fullName']) && (isset($contact['firstName']) || isset($contact['lastName']))) {
                        $contact['fullName'] = trim(($contact['firstName'] ?? '') . ' ' . ($contact['lastName'] ?? ''));
                    }
                    $masterRow = DB::table('tour_bookings_master')->where('id', $octoBooking->tour_booking_id)->first();

                    $fieldMap  = [
                        'fullName'     => ['col' => 'name',         'old_key' => 'name'],
                        'emailAddress' => ['col' => 'email',        'old_key' => 'email'],
                        'phoneNumber'  => ['col' => 'phone_number', 'old_key' => 'phone_number'],
                    ];

                    $oldValues = [];
                    $newValues = [];
                    foreach ($fieldMap as $requestKey => ['col' => $col, 'old_key' => $oldKey]) {
                        if (!isset($contact[$requestKey])) continue;
                        $newVal = $contact[$requestKey];
                        $oldVal = $masterRow?->$col ?? '';
                        $masterUpdates[$col] = $newVal;
                        $newValues[$oldKey === 'name' ? 'new_name' : ($oldKey === 'email' ? 'new_email' : 'new_phone')] = $newVal;
                        $oldValues[$oldKey] = ($oldVal !== $newVal) ? $oldVal : '';
                    }

                    if (array_filter($oldValues)) {
                        DB::table('tour_booking_history')->insert([
                            'tour_booking_id'        => $octoBooking->tour_booking_id,
                            'operation'              => 'Customer Info Update',
                            'details'                => json_encode(array_merge($oldValues, $newValues), JSON_NUMERIC_CHECK),
                            'operation_at'           => mysqlDT(),
                            'operation_by'           => $affiliate->id,
                            'operation_by_user_type' => 'Affiliate',
                            'created_at'             => now(),
                            'updated_at'             => now(),
                        ]);
                    }
                }

                if (request()->has('notes')) {
                    $note                  = request()->input('notes');
                    $masterUpdates['note'] = $note;

                    if (!empty($note) && $note !== $octoBooking->notes) {
                        DB::table('tour_booking_history')->insert([
                            'tour_booking_id'        => $octoBooking->tour_booking_id,
                            'operation'              => 'Comments',
                            'details'                => $note,
                            'operation_at'           => mysqlDT(),
                            'operation_by'           => $affiliate->id,
                            'operation_by_user_type' => 'Affiliate',
                            'created_at'             => now(),
                            'updated_at'             => now(),
                        ]);
                    }
                }

                if (!empty($masterUpdates)) {
                    DB::table('tour_bookings_master')
                        ->where('id', $octoBooking->tour_booking_id)
                        ->update($masterUpdates);
                }
            }

            // --- Structural changes for CONFIRMED (slot reschedule + pax change) ---
            if ($octoBooking->status === 'CONFIRMED' && $isStructuralChange && $octoBooking->tour_booking_id) {

                $newAvailabilityId = request()->input('availabilityId');
                $newUnitItems      = request()->input('unitItems');

                if ($newUnitItems !== null && (empty($newUnitItems) || !is_array($newUnitItems))) {
                    return OctoErrorResponse::unprocessableEntity('At least one guest must remain in the booking.');
                }

                // Resolve target slot (new if rescheduling, current otherwise)
                $isSlotChanging = $newAvailabilityId !== null
                    && (string) $newAvailabilityId !== (string) $octoBooking->tour_slot_id;

                if ($isSlotChanging) {
                    $targetSlot = DB::table('tour_slot_times')->where('id', $newAvailabilityId)->first();
                    if (!$targetSlot) {
                        return OctoErrorResponse::invalidAvailabilityId((string) $newAvailabilityId);
                    }
                    if (in_array($targetSlot->bookable_status, ['Closed', 'Only by Phone'], true)) {
                        return OctoErrorResponse::unprocessableEntity('The selected time slot is not available for booking.');
                    }
                    $tz        = $operator?->timezone ?? 'UTC';
                    $newSlotStart = Carbon::createFromFormat('Y-m-d H:i:s', $targetSlot->date . ' ' . $targetSlot->time_from, $tz);
                    if ($newSlotStart->lte(Carbon::now($tz))) {
                        return OctoErrorResponse::unprocessableEntity('The selected time slot is in the past.');
                    }
                    // New slot must also clear the OTA cutoff window — mirrors Bokun amendment check
                    $newSlotCutoffHours = isset($cutoffHours) ? $cutoffHours : 0;
                    if ($newSlotCutoffHours > 0 && $newSlotStart->lt(Carbon::now($tz)->addHours($newSlotCutoffHours))) {
                        return OctoErrorResponse::unprocessableEntity('The selected time slot is within the OTA booking cutoff window and cannot be used for rescheduling.');
                    }
                } else {
                    $targetSlot = DB::table('tour_slot_times')->where('id', $octoBooking->tour_slot_id)->first();
                }

                // Resolve new tourists/infants from new unit items (or keep current)
                $newTourists      = $octoBooking->tourists;
                $newInfants       = $octoBooking->infants;
                $newRateCountById = null;
                $formattedItems   = null;

                if ($newUnitItems !== null) {
                    // Normalise any rate id submitted from a different affiliate's rate sheet
                    // (Bokun exposes our raw TourPackageRate id to the OTA as pricingCategoryId
                    // — see BokunRepository::productGetAvailable — so a client may genuinely
                    // still hold the old Bokun-scoped id for a pre-cutover booking, with no
                    // reason to know it changed) to this affiliate's equivalent via mapping_key,
                    // before validating. A truly invalid id is left untouched and still
                    // rejected below exactly as before.
                    foreach ($newUnitItems as &$item) {
                        $rawUnitId = (string) ($item['unitId'] ?? '');
                        if ($rawUnitId === '' || !ctype_digit($rawUnitId)) continue;
                        try {
                            $item['unitId'] = (string) OctoLegacyBookingBackfillService::translateRateId(
                                (int) $rawUnitId,
                                $octoBooking->tour_package_id,
                                $affiliate->id
                            )->id;
                        } catch (RuntimeException $exception) {
                            // Leave as submitted — invalidUnitId below reports it clearly.
                        }
                    }
                    unset($item);

                    $unitIds          = array_column($newUnitItems, 'unitId');
                    $newRateCountById = array_count_values($unitIds);

                    $rates = TourPackageRate::whereIn('id', array_keys($newRateCountById))
                        ->where('tour_package_id', $octoBooking->tour_package_id)
                        ->where('affiliate_id', $affiliate->id)
                        ->where('status', 'Active')
                        ->get()
                        ->keyBy('id');

                    $newTourists = 0;
                    $newInfants  = 0;
                    foreach ($newRateCountById as $rateId => $qty) {
                        $rate = $rates->get($rateId);
                        if (!$rate) {
                            return OctoErrorResponse::invalidUnitId((string) $rateId, "Unit ID '{$rateId}' is not valid for this product.");
                        }
                        $rate->is_infant_rate ? ($newInfants += $qty) : ($newTourists += $qty);
                    }

                    $existingByUuid = collect($octoBooking->unit_items ?? [])->keyBy('uuid');
                    $chars          = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
                    $formattedItems = array_map(function ($item) use ($existingByUuid, $chars) {
                        $itemUuid    = $item['uuid'] ?? null;
                        $existing    = $itemUuid ? $existingByUuid->get($itemUuid) : null;
                        $supplierRef = $existing['supplierReference'] ?? '';
                        if (!$supplierRef) {
                            for ($i = 0; $i < 6; $i++) {
                                $supplierRef .= $chars[random_int(0, strlen($chars) - 1)];
                            }
                        }
                        return [
                            'uuid'              => $itemUuid ?? (string) Str::uuid(),
                            'resellerReference' => $existing['resellerReference'] ?? ($item['uuid'] ?? ''),
                            'supplierReference' => $supplierRef,
                            'unitId'            => (string) ($item['unitId'] ?? ''),
                            'contact'           => $item['contact'] ?? ($existing['contact'] ?? null),
                        ];
                    }, $newUnitItems);
                }

                // Seat check — Bokun pattern: slot change = full count; same slot = net additional
                $affiliateLimit = DB::table('affiliate_slot_times as ast')
                    ->join('tour_slot_times_affiliate as tsta', 'tsta.id', '=', 'ast.tour_slot_time_id')
                    ->where('tsta.tour_package_id', $octoBooking->tour_package_id)
                    ->where('tsta.time_from', $targetSlot->time_from)
                    ->where('tsta.status', 'Active')
                    ->where('tsta.deleted_by', 0)
                    ->where('ast.affiliate_id', $affiliate->id)
                    ->where('ast.deleted_by', 0)
                    ->value('ast.seat_limit');

                if ($affiliateLimit === null || (int) $affiliateLimit === 0) {
                    return OctoErrorResponse::unprocessableEntity('No seat allocation found for this time slot.');
                }

                $remaining = OctoService::resolveRemainingSeats(
                    $targetSlot->id,
                    $affiliate->id,
                    (int) $affiliateLimit,
                    (int) $targetSlot->time_slot_seats,
                    $octoBooking->tour_package_id,
                );

                if ($isSlotChanging) {
                    if ($newTourists > $remaining) {
                        return OctoErrorResponse::unprocessableEntity('Not enough seats available on the requested time slot.');
                    }
                } else {
                    $additionalNeeded = max(0, $newTourists - $octoBooking->tourists);
                    if ($additionalNeeded > $remaining) {
                        return OctoErrorResponse::unprocessableEntity('Not enough seats available for the requested number of guests.');
                    }
                }

                $oldTourists   = $octoBooking->tourists;
                $oldSlotId     = $octoBooking->tour_slot_id;
                $tourBookingId = $octoBooking->tour_booking_id;

                // Cannot reschedule across calendar years — rate groups are year-specific
                if ($isSlotChanging) {
                    $oldSlotRow = DB::table('tour_slot_times')->where('id', $oldSlotId)->value('date');
                    if ($oldSlotRow && date('Y', strtotime($oldSlotRow)) !== date('Y', strtotime($targetSlot->date))) {
                        return OctoErrorResponse::unprocessableEntity('Rescheduling across calendar years is not allowed.');
                    }
                }

                DB::transaction(function () use (
                    $octoBooking, $affiliate, $tourBookingId,
                    $targetSlot, $isSlotChanging, $oldTourists, $oldSlotId,
                    $newTourists, $newInfants, $newRateCountById, $formattedItems
                ) {
                    // ── Reschedule ────────────────────────────────────────────────────────
                    if ($isSlotChanging) {
                        $oldSlot = DB::table('tour_slot_times')->where('id', $oldSlotId)->first(['date', 'time_from']);

                        DB::table('tour_bookings_master')
                            ->where('id', $tourBookingId)
                            ->update([
                                'tour_slot_id'         => $targetSlot->id,
                                'updated_by'           => $affiliate->id,
                                'updated_by_user_type' => 'Affiliate',
                                'updated_at'           => now(),
                            ]);

                        // Transfer seats: release old slot, claim new slot (tourists only)
                        if ($oldTourists > 0) {
                            TimeSlotAvailabilityService::updateSlotSummary($oldSlotId, -$oldTourists);
                            TimeSlotAvailabilityService::updateSlotSummary($targetSlot->id, $oldTourists);
                            $oldSlot = DB::table('tour_slot_times')->where('id', $oldSlotId)->first();
                            $this->dispatchAvailabilityNotifications($affiliate->id, (string) $octoBooking->tour_package_id, $oldSlot->date);
                            $this->dispatchAvailabilityNotifications($affiliate->id, (string) $octoBooking->tour_package_id, $targetSlot->date);
                        }

                        // Proper reschedule history via shared service (operation: 'Rescheduled')
                        $createdByWithName = BookingService::createdUserInfo(false, null, $affiliate->id, 'Affiliate');
                        HistoryService::storeRescheduledDataInHistory(
                            $tourBookingId,
                            $oldSlot,
                            $targetSlot,
                            $createdByWithName,
                            $affiliate->id,
                            'Affiliate'
                        );

                        // Slot open/close state — mirrors TourBookingRepository::reschedule()
                        // Old slot: reopen if no other active bookings remain and package uses slot_close_on_booking
                        TimeSlotService::reopenSlotOnCancellation($octoBooking->tour_package_id, $oldSlotId, $tourBookingId);
                        // New slot: close if package uses slot_close_on_booking
                        TimeSlotService::closeSlotOnBooking($octoBooking->tour_package_id, $targetSlot->id);
                    }

                    // ── Pax change — fully consistent with Bokun bookingAmendment ─────────
                    if ($newRateCountById !== null) {
                        $existingTransacts = DB::table('tour_bookings_transact')
                            ->where('tour_booking_id', $tourBookingId)
                            ->where('deleted_by', 0)
                            ->get()
                            ->keyBy('tour_package_rate_id');

                        $referenceTransact = $existingTransacts->first();
                        $package           = TourPackage::find($octoBooking->tour_package_id);

                        $oldRateCountById = $existingTransacts->mapWithKeys(fn ($t) => [
                            $t->tour_package_rate_id => (int) ($t->is_infant_rate ? $t->infants : $t->tourists),
                        ])->toArray();

                        $allRateIds = array_unique(array_merge(array_keys($newRateCountById), array_keys($oldRateCountById)));

                        // Separate into deleted and added (mirrors Bokun's $deletedPax / $addedPax)
                        $deletedRates = [];
                        $addedRates   = [];
                        foreach ($allRateIds as $rateId) {
                            $diff = ((int) ($newRateCountById[$rateId] ?? 0)) - ((int) ($oldRateCountById[$rateId] ?? 0));
                            if ($diff < 0) $deletedRates[$rateId] = abs($diff);
                            if ($diff > 0) $addedRates[$rateId]   = $diff;
                        }

                        // Payment via — read from the original completed transaction (mirrors Bokun)
                        $paymentVia = DB::table('transactions')
                            ->where('tour_booking_id', $tourBookingId)
                            ->where('status', 'Completed')
                            ->whereNull('charge_type')
                            ->orderBy('id')
                            ->value('via') ?? $affiliate->name;

                        $paxCreatedByWithName = BookingService::createdUserInfo(false, null, $affiliate->id, 'Affiliate');

                        // ── Delete loop ─────────────────────────────────────────────────────
                        $totalRefundAmount     = 0.0;
                        $totalRefundPermitFees = 0.0;
                        $netDeletedTourists    = 0;
                        $netDeletedInfants     = 0;
                        $deletedLabels         = [];
                        $pendingRefundHistories = [];
                        // Holds the ID of the old group transact row when a tier change is detected.
                        // The add loop will UPDATE this row in-place instead of inserting a new one,
                        // matching non-OCTO changePeopleBooking which never inserts for group tier changes.
                        $oldGroupTransactId  = null;
                        $isGroupTierChange   = false; // true when a group rate tier-change UPDATE fires

                        foreach ($deletedRates as $rateId => $count) {
                            $transact = $existingTransacts->get($rateId);
                            if (!$transact) continue;

                            $isInfant    = (int) $transact->is_infant_rate === 1;
                            $isGroupRate = (int) $transact->is_group_rate === 1;
                            // Group rate: refund flat price only when the entire tier is removed (tier change);
                            // reducing pax within the same tier is free — price is flat per tier.
                            $isTierRemoved = $isGroupRate && (($newRateCountById[$rateId] ?? 0) === 0);
                            $multiplier  = $isGroupRate ? ($isTierRemoved ? 1 : 0) : $count;
                            $unitRate    = (float) $transact->rate + (float) $transact->additional_charge + (float) $transact->permit_fee;
                            $perRate     = $unitRate * (float) ($transact->processing_charge_percentage ?? 0) / 100;
                            $total       = round(($unitRate + $perRate) * $multiplier, 2);

                            $newTransactTourists = $isInfant ? (int) $transact->tourists : max(0, (int) $transact->tourists - $count);
                            $newTransactInfants  = $isInfant ? max(0, (int) $transact->infants - $count) : (int) $transact->infants;
                            $newTransactTotal    = max(0, round((float) $transact->total - $total, 2));

                            if ($isTierRemoved) {
                                // Group tier change: save the old row ID so the add loop can update
                                // it in-place with the new tier's data. No DB write here.
                                $oldGroupTransactId = $transact->id;
                            } else {
                                $updTransact = [
                                    'tourists'             => $newTransactTourists,
                                    'infants'              => $newTransactInfants,
                                    'total'                => $newTransactTotal,
                                    'updated_by'           => $affiliate->id,
                                    'updated_by_user_type' => 'Affiliate',
                                    'updated_at'           => now(),
                                ];
                                // Reset check_in_status when all pax removed from this rate group (mirrors Bokun)
                                if ($newTransactTourists === 0 && $newTransactInfants === 0) {
                                    $updTransact['check_in_status'] = '';
                                }
                                DB::table('tour_bookings_transact')->where('id', $transact->id)->update($updTransact);
                            }

                            $label           = optional(TourPackageRate::withTrashed()->find($rateId))->rate_for ?? 'person';
                            $peopleText      = $isGroupRate ? "Person(s) {$count}" : "{$label} person {$count}";
                            $deletedLabels[] = $peopleText;

                            $totalRefundAmount     += $total;
                            $totalRefundPermitFees += round((float) $transact->permit_fee * $count, 2);
                            $isInfant ? ($netDeletedInfants += $count) : ($netDeletedTourists += $count);

                            // Collect per-rate refund data — linked to transactionId after it's created
                            $pendingRefundHistories[] = [
                                'total'      => $total,
                                'label'      => $peopleText,
                                'permitFees' => round((float) $transact->permit_fee * $count, 2),
                            ];
                        }

                        // ── Add loop ────────────────────────────────────────────────────────
                        $totalAddAmount     = 0.0;
                        $totalAddPermitFees = 0.0;
                        $netAddedTourists   = 0;
                        $netAddedInfants    = 0;
                        $addedLabels        = [];

                        foreach ($addedRates as $rateId => $count) {
                            $transact = $existingTransacts->get($rateId);

                            if ($transact) {
                                // Existing rate group — update transact row
                                $isInfant    = (int) $transact->is_infant_rate === 1;
                                $isGroupRate = (int) $transact->is_group_rate === 1;
                                // Group rate: pax change within same tier carries no price delta (flat rate).
                                $multiplier  = $isGroupRate ? 0 : $count;
                                $unitRate    = (float) $transact->rate + (float) $transact->additional_charge + (float) $transact->permit_fee;
                                $perRate     = $unitRate * (float) ($transact->processing_charge_percentage ?? 0) / 100;
                                $addAmount   = round(($unitRate + $perRate) * $multiplier, 2);

                                DB::table('tour_bookings_transact')->where('id', $transact->id)->update([
                                    'tourists'             => $isInfant ? (int) $transact->tourists : (int) $transact->tourists + $count,
                                    'infants'              => $isInfant ? (int) $transact->infants + $count : (int) $transact->infants,
                                    'total'                => round((float) $transact->total + $addAmount, 2),
                                    'updated_by'           => $affiliate->id,
                                    'updated_by_user_type' => 'Affiliate',
                                    'updated_at'           => now(),
                                ]);

                                $permitFeeForAccum = (float) $transact->permit_fee;
                            } else {
                                // New rate group (per-person: new rate type added; or group: tier change)
                                $rateObj = TourPackageRate::where('id', $rateId)
                                    ->where('tour_package_id', $octoBooking->tour_package_id)
                                    ->where('affiliate_id', $affiliate->id)
                                    ->where('status', 'Active')
                                    ->first();
                                if (!$rateObj) continue;

                                $isInfant    = (int) $rateObj->is_infant_rate === 1;
                                $isGroupRate = (int) $rateObj->is_group_rate === 1;
                                // Group rate: flat price × 1 (not per-head); exclude permit_fee and tax.
                                $multiplier  = $isGroupRate ? 1 : $count;
                                $unitRate    = (float) $rateObj->rate + (float) $rateObj->additional_charge + ($isGroupRate ? 0.0 : (float) $rateObj->permit_fee);
                                $perRate     = $isGroupRate ? 0.0 : $unitRate * (float) ($rateObj->processing_charge_percentage ?? 0) / 100;
                                $addAmount   = round(($unitRate + $perRate) * $multiplier, 2);

                                if ($isGroupRate && $oldGroupTransactId !== null) {
                                    // Group tier change: UPDATE the old transact row in-place with the
                                    // new tier's data. Matches non-OCTO updateTblTransactChangeGroupPeople —
                                    // only ONE active transact row ever exists for a group booking.
                                    DB::table('tour_bookings_transact')->where('id', $oldGroupTransactId)->update([
                                        'tour_package_rate_id'         => $rateObj->id,
                                        'rate'                         => $rateObj->rate,
                                        'additional_charge'            => $rateObj->additional_charge ?? 0,
                                        'permit_fee'                   => 0,
                                        'additional_tax_percentage'    => 0,
                                        'is_group_rate'                => 1,
                                        'tourists'                     => $isInfant ? 0 : $count,
                                        'infants'                      => $isInfant ? $count : 0,
                                        'total'                        => $addAmount,
                                        'updated_by'                   => $affiliate->id,
                                        'updated_by_user_type'         => 'Affiliate',
                                        'updated_at'                   => now(),
                                    ]);
                                    $oldGroupTransactId = null;
                                    $isGroupTierChange  = true;
                                } else {
                                    // Per-person new rate type: INSERT a new transact row.
                                    DB::table('tour_bookings_transact')->insert([
                                        'tour_booking_id'              => $tourBookingId,
                                        'tour_package_rate_id'         => $rateObj->id,
                                        'tourists'                     => $isInfant ? 0 : $count,
                                        'infants'                      => $isInfant ? $count : 0,
                                        'is_infant_rate'               => $isInfant ? 1 : 0,
                                        'is_group_rate'                => $isGroupRate ? 1 : 0,
                                        'rate'                         => $rateObj->rate,
                                        'additional_charge'            => $rateObj->additional_charge ?? 0,
                                        'permit_fee'                   => $isGroupRate ? 0 : ($rateObj->permit_fee ?? 0),
                                        'additional_tax_percentage'    => $isGroupRate ? 0 : ($rateObj->additional_tax_percentage ?? 0),
                                        'processing_charge_percentage' => $rateObj->processing_charge_percentage ?? 0,
                                        'nat_fees_percentage'          => $referenceTransact->nat_fees_percentage ?? $package->service_commission_percentage ?? 0,
                                        'stripe_fees_percentage'       => $referenceTransact->stripe_fees_percentage ?? $package->stripe_commission_percentage ?? 0,
                                        'partner_fees_amount'          => 0,
                                        'total'                        => $addAmount,
                                        'check_in_status'              => '',
                                        'deleted_by'                   => 0,
                                        'created_by'                   => $affiliate->id,
                                        'created_by_user_type'         => 'Affiliate',
                                        'updated_by'                   => $affiliate->id,
                                        'updated_by_user_type'         => 'Affiliate',
                                        'created_at'                   => now(),
                                        'updated_at'                   => now(),
                                    ]);
                                }

                                $permitFeeForAccum = $isGroupRate ? 0.0 : (float) $rateObj->permit_fee;
                            }

                            $label         = optional(TourPackageRate::withTrashed()->find($rateId))->rate_for ?? 'person';
                            $addedLabels[] = $isGroupRate ? "Person(s) {$count}" : "{$label} person {$count}";

                            $totalAddAmount     += $addAmount;
                            $totalAddPermitFees += round($permitFeeForAccum * $count, 2);
                            $isInfant ? ($netAddedInfants += $count) : ($netAddedTourists += $count);
                        }

                        // Net financial amount (mirrors Bokun)
                        $netAmount = round($totalAddAmount - $totalRefundAmount, 2);

                        // ── Update master record ────────────────────────────────────────────
                        $tourBooking = TourBooking::find($tourBookingId);
                        if ($tourBooking) {
                            $tourBooking->refresh();
                            $masterUpd = [
                                'tourists'             => max(0, $tourBooking->tourists + $netAddedTourists - $netDeletedTourists),
                                'infants'              => max(0, $tourBooking->infants  + $netAddedInfants  - $netDeletedInfants),
                                'updated_by'           => $affiliate->id,
                                'updated_by_user_type' => 'Affiliate',
                            ];
                            if ($netAmount > 0) {
                                // Net payment: both total and booking_total increase
                                $masterUpd['total']         = round($tourBooking->total         + $netAmount, 2);
                                $masterUpd['booking_total'] = round($tourBooking->booking_total + $netAmount, 2);
                            } elseif ($netAmount < 0) {
                                // Net refund: only total decreases; booking_total unchanged (mirrors Bokun)
                                $masterUpd['total'] = max(0, round($tourBooking->total + $netAmount, 2));
                            }
                            $tourBooking->update($masterUpd);

                            // ── Transaction — ONE net record; capture ID to link to history ──
                            $transactionId = null;
                            if ($netAmount != 0) {
                                $transactionId = BokunService::storePaymentInTransaction(
                                    $tourBookingId,
                                    round(abs($netAmount), 2),
                                    0,
                                    $paymentVia,
                                    $netAmount > 0 ? 'Completed' : 'Refund',
                                    $affiliate->id,
                                    strtoupper($affiliate->name)
                                );
                            }

                            // ── Refund history — delete-only: one entry per rate group ────
                            $paxHistoryId = 0;
                            // Group tier change: history stores NET amounts (non-OCTO uses the rate diff,
                            // not the full tier prices). Per-person: gross add/delete amounts like non-OCTO.
                            $histPaymentTotal = $isGroupTierChange ? max(0.0, $netAmount)  : $totalAddAmount;
                            $histRefundTotal  = $isGroupTierChange ? max(0.0, -$netAmount) : $totalRefundAmount;
                            if (!empty($pendingRefundHistories) && empty($addedRates)) {
                                $tourBooking->refresh();
                                foreach ($pendingRefundHistories as $refundHistory) {
                                    // Skip 0-amount entries: group same-tier pax decrease carries
                                    // no refund (flat price unchanged), so no history row needed.
                                    if ($refundHistory['total'] <= 0) continue;
                                    BokunService::storeRefundDataInHistory(
                                        $tourBookingId,
                                        $tourBooking->tourists + $tourBooking->infants,
                                        $paxCreatedByWithName,
                                        'Full Refund minus Fees',
                                        $refundHistory['total'],
                                        $paymentVia,
                                        0,
                                        $refundHistory['label'],
                                        $refundHistory['total'],
                                        0,
                                        $affiliate->id,
                                        0,
                                        $transactionId
                                    );
                                }
                            }

                            // ── Refund history — mixed with net refund: ONE combined entry ─
                            if (!empty($addedRates) && !empty($deletedRates) && $netAmount < 0) {
                                $tourBooking->refresh();
                                $netPermitFeeRefund = max(0, round($totalRefundPermitFees - $totalAddPermitFees, 2));
                                $paxHistoryId = BokunService::storeRefundDataInHistory(
                                    $tourBookingId,
                                    $tourBooking->tourists + $tourBooking->infants,
                                    $paxCreatedByWithName,
                                    'Partial Refund',
                                    abs($netAmount),
                                    $paymentVia,
                                    0,
                                    implode(', ', $deletedLabels),
                                    abs($netAmount),
                                    $netPermitFeeRefund,
                                    $affiliate->id,
                                    0,
                                    $transactionId,
                                    $histPaymentTotal,
                                    $histRefundTotal
                                );
                            }

                            // ── Payment history — add-only or mixed net payment ────────────
                            if ($netAmount > 0) {
                                $tourBooking->refresh();
                                $paymentTicketCost = !empty($deletedRates) ? $netAmount : $totalAddAmount;
                                $paxHistoryId = BokunService::storePaymentInHistory(
                                    $tourBookingId,
                                    $tourBooking->tourists + $tourBooking->infants,
                                    [
                                        'amount'              => $netAmount,
                                        'payment_type'        => $paymentVia,
                                        'payment_method'      => 'Full Payment',
                                        'paymenttotal'        => $histPaymentTotal,
                                        'refundtotal'         => $histRefundTotal,
                                        'swipeHistoryId'      => 0,
                                        'discount_promo_code' => '',
                                    ],
                                    0,
                                    $paxCreatedByWithName,
                                    $paymentTicketCost,
                                    0,
                                    $paymentVia,
                                    'Full Payment',
                                    $affiliate->id,
                                    $transactionId
                                );
                            }

                            // ── Tourist-change history ─────────────────────────────────────
                            // Fires for add-only and mixed cases always.
                            // Also fires for delete-only when netAmount=0 (group same-tier pax
                            // decrease): refund history is skipped for those, but master tourists
                            // changed so we still need an audit trail here.
                            // Per-person delete-only is excluded: it has netAmount != 0 and its
                            // refund history entry already serves as the change record.
                            if (!empty($addedRates) || ($netAmount == 0 && !empty($deletedRates))) {
                                $tourBooking->refresh();

                                // Group tier change: non-OCTO stores only the NET pax difference
                                // (tourBooking->tourists vs new pax), not the full old/new tier counts.
                                // e.g. 5-pax tier B → 3-pax tier A: deletedTourits=2, deleted=["Person(s) 2"], added=[]
                                // Same-tier and per-person cases are already NET, so no adjustment needed.
                                if ($isGroupTierChange) {
                                    $netPaxChange       = $netAddedTourists - $netDeletedTourists;
                                    $histAddedCount     = max(0, $netPaxChange);
                                    $histDeletedCount   = max(0, -$netPaxChange);
                                    $histAddedTexts     = $histAddedCount   > 0 ? ["Person(s) {$histAddedCount}"]   : [];
                                    $histDeletedTexts   = $histDeletedCount > 0 ? ["Person(s) {$histDeletedCount}"] : [];
                                    $histAddedInfants   = 0;
                                    $histDeletedInfants = 0;
                                } else {
                                    $histAddedCount     = $netAddedTourists  + $netAddedInfants;
                                    $histDeletedCount   = $netDeletedTourists + $netDeletedInfants;
                                    $histAddedTexts     = $addedLabels;
                                    $histDeletedTexts   = $deletedLabels;
                                    $histAddedInfants   = $netAddedInfants;
                                    $histDeletedInfants = $netDeletedInfants;
                                }

                                HistoryService::changeTouristInHistory(
                                    $tourBookingId,
                                    [
                                        'tourists'            => $tourBooking->tourists + $tourBooking->infants,
                                        'discount_percentage' => 0,
                                        'discount2_value'     => 0,
                                        'addedpeoplesetexts'  => $histAddedTexts,
                                        'deletedpeoplestexts' => $histDeletedTexts,
                                        'addedtourists'       => $histAddedCount,
                                        'deletedtourits'      => $histDeletedCount,
                                        'addedinfants'        => $histAddedInfants,
                                        'deletedinfants'      => $histDeletedInfants,
                                        'paymenttotal'        => $histPaymentTotal,
                                        'refundtotal'         => $histRefundTotal,
                                        'txnhistoryid'        => $paxHistoryId,
                                    ],
                                    $paxCreatedByWithName,
                                    $affiliate->id,
                                    'Affiliate'
                                );
                            }
                        }

                        // ── Slot seat summary (tourists only — infants excluded, mirrors Bokun) ─
                        $seatsDiff = $netAddedTourists - $netDeletedTourists;
                        if ($seatsDiff !== 0) {
                            $summarySlotId = $isSlotChanging ? $targetSlot->id : $oldSlotId;
                            TimeSlotAvailabilityService::updateSlotSummary($summarySlotId, $seatsDiff);
                            $this->dispatchAvailabilityNotifications($affiliate->id, (string) $octoBooking->tour_package_id, $targetSlot->date);
                        }
                    }

                    // Update octo_bookings with new slot / pax / unit items
                    $octoUpdates = [];
                    if ($isSlotChanging)          $octoUpdates['tour_slot_id'] = $targetSlot->id;
                    if ($newRateCountById !== null) {
                        $octoUpdates['tourists']   = $newTourists;
                        $octoUpdates['infants']    = $newInfants;
                        $octoUpdates['unit_items'] = $formattedItems;
                    }
                    if (!empty($octoUpdates)) {
                        $octoBooking->update($octoUpdates);
                    }
                });

                $octoBooking->refresh()->load('tourBooking');
            }

            // --- Fields allowed on ON_HOLD only ---
            if ($octoBooking->status === 'ON_HOLD') {

                // Extend hold expiry
                if (request()->has('expirationMinutes')) {
                    $minutes             = max(1, (int) (request()->input('expirationMinutes') ?? 30));
                    $updates['expire_at'] = now()->addMinutes($minutes);
                }

                $newUnitItems      = request()->input('unitItems');
                $newAvailabilityId = request()->input('availabilityId');

                // Reject empty unitItems before touching the slot — clearer error for the caller.
                if ($newUnitItems !== null && (empty($newUnitItems) || !is_array($newUnitItems))) {
                    return OctoErrorResponse::unprocessableEntity('At least one guest must remain in the booking.');
                }

                // Only resolve the slot when unitItems or availabilityId is in the request —
                // a notes/expirationMinutes-only update must not fail because of a missing slot.
                $targetSlot = null;
                if ($newUnitItems !== null || $newAvailabilityId !== null) {
                    $targetSlotId = $newAvailabilityId ?? $octoBooking->tour_slot_id;
                    $targetSlot   = DB::table('tour_slot_times')->where('id', $targetSlotId)->first();

                    if (!$targetSlot) {
                        return OctoErrorResponse::invalidAvailabilityId((string) $targetSlotId);
                    }
                }

                // Validate the new slot if the availability is being changed
                if ($newAvailabilityId !== null && (string) $newAvailabilityId !== (string) $octoBooking->tour_slot_id) {
                    if (in_array($targetSlot->bookable_status, ['Closed', 'Only by Phone'], true)) {
                        return OctoErrorResponse::unprocessableEntity('The selected time slot is not available for booking.');
                    }

                    $operator  = TourOperator::first();
                    $timezone  = $operator?->timezone ?? 'UTC';
                    $slotStart = Carbon::createFromFormat('Y-m-d H:i:s', $targetSlot->date . ' ' . $targetSlot->time_from, $timezone);
                    if ($slotStart->lte(Carbon::now($timezone))) {
                        return OctoErrorResponse::unprocessableEntity('The selected time slot is in the past.');
                    }

                    // Mirror the CONFIRMED path: reject new slot within the OTA cutoff window
                    $holdCutoffHours = isset($cutoffHours) ? $cutoffHours : (int) ($octoBooking->tourPackage?->ota_duration ?? 0);
                    if ($holdCutoffHours > 0 && $slotStart->lt(Carbon::now($timezone)->addHours($holdCutoffHours))) {
                        return OctoErrorResponse::unprocessableEntity('The selected time slot is within the OTA booking cutoff window and cannot be used for rescheduling.');
                    }

                    $updates['tour_slot_id'] = $targetSlot->id;
                }

                // Resolve updated tourists/infants and unit items
                if ($newUnitItems !== null) {
                    $unitIds       = array_column($newUnitItems, 'unitId');
                    $rateCountById = array_count_values($unitIds);

                    $rates = TourPackageRate::whereIn('id', array_keys($rateCountById))
                        ->where('tour_package_id', $octoBooking->tour_package_id)
                        ->where('affiliate_id', $affiliate->id)
                        ->where('status', 'Active')
                        ->get()
                        ->keyBy('id');

                    $newTourists = 0;
                    $newInfants  = 0;
                    foreach ($rateCountById as $rateId => $qty) {
                        $rate = $rates->get($rateId);
                        if (!$rate) {
                            return OctoErrorResponse::invalidUnitId((string) $rateId, "Unit ID '{$rateId}' is not valid for this product.");
                        }
                        $rate->is_infant_rate ? ($newInfants += $qty) : ($newTourists += $qty);
                    }

                    // Seat check
                    // $isSlotChanging is true when availabilityId resolves to a different slot.
                    // The two cases need different comparisons (mirrors Bokun bookingAmendment logic):
                    //
                    // Slot change:  the current booking is still on the OLD slot, so resolveRemainingSeats
                    //               on the NEW slot does NOT deduct this booking's tourists.
                    //               → ALL new tourists must fit: $newTourists > $remaining
                    //
                    // Same slot:    the current ON_HOLD booking IS counted in resolveRemainingSeats
                    //               on the same slot, so remaining is already reduced by its tourists.
                    //               → only the NET additional seats are needed: ($newTourists - current) > $remaining
                    $isSlotChanging = isset($updates['tour_slot_id']);

                    $affiliateLimit = DB::table('affiliate_slot_times as ast')
                        ->join('tour_slot_times_affiliate as tsta', 'tsta.id', '=', 'ast.tour_slot_time_id')
                        ->where('tsta.tour_package_id', $octoBooking->tour_package_id)
                        ->where('tsta.time_from', $targetSlot->time_from)
                        ->where('tsta.status', 'Active')
                        ->where('tsta.deleted_by', 0)
                        ->where('ast.affiliate_id', $affiliate->id)
                        ->where('ast.deleted_by', 0)
                        ->value('ast.seat_limit');

                    if ($affiliateLimit === null || (int) $affiliateLimit === 0) {
                        return OctoErrorResponse::unprocessableEntity('No seat allocation found for this time slot.');
                    }

                    $remaining = OctoService::resolveRemainingSeats(
                        $targetSlot->id,
                        $affiliate->id,
                        (int) $affiliateLimit,
                        (int) $targetSlot->time_slot_seats,
                        $octoBooking->tour_package_id,
                    );

                    if ($isSlotChanging) {
                        if ($newTourists > $remaining) {
                            return OctoErrorResponse::unprocessableEntity('Not enough seats available on the requested time slot.');
                        }
                    } else {
                        $additionalNeeded = max(0, $newTourists - $octoBooking->tourists);
                        if ($additionalNeeded > $remaining) {
                            return OctoErrorResponse::unprocessableEntity('Not enough seats available for the requested number of guests.');
                        }
                    }

                    // Merge: existing items keep their uuid/supplierReference; new items get fresh ones
                    $existingByUuid = collect($octoBooking->unit_items ?? [])->keyBy('uuid');
                    $chars          = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

                    $formattedItems = array_map(function ($item) use ($existingByUuid, $chars) {
                        $itemUuid = $item['uuid'] ?? null;
                        $existing = $itemUuid ? $existingByUuid->get($itemUuid) : null;

                        $supplierRef = $existing['supplierReference'] ?? '';
                        if (!$supplierRef) {
                            for ($i = 0; $i < 6; $i++) {
                                $supplierRef .= $chars[random_int(0, strlen($chars) - 1)];
                            }
                        }

                        return [
                            'uuid'              => $itemUuid ?? (string) Str::uuid(),
                            'resellerReference' => $existing['resellerReference'] ?? ($item['uuid'] ?? ''),
                            'supplierReference' => $supplierRef,
                            'unitId'            => (string) ($item['unitId'] ?? ''),
                            'contact'           => $item['contact'] ?? ($existing['contact'] ?? null),
                        ];
                    }, $newUnitItems);

                    $updates['unit_items'] = $formattedItems;
                    $updates['tourists']   = $newTourists;
                    $updates['infants']    = $newInfants;
                } elseif (isset($updates['tour_slot_id'])) {
                    // Slot changed but pax unchanged — check seats on new slot with current tourist count
                    $affiliateLimit = DB::table('affiliate_slot_times as ast')
                        ->join('tour_slot_times_affiliate as tsta', 'tsta.id', '=', 'ast.tour_slot_time_id')
                        ->where('tsta.tour_package_id', $octoBooking->tour_package_id)
                        ->where('tsta.time_from', $targetSlot->time_from)
                        ->where('tsta.status', 'Active')
                        ->where('tsta.deleted_by', 0)
                        ->where('ast.affiliate_id', $affiliate->id)
                        ->where('ast.deleted_by', 0)
                        ->value('ast.seat_limit');

                    if ($affiliateLimit === null || (int) $affiliateLimit === 0) {
                        return OctoErrorResponse::unprocessableEntity('No seat allocation found for this time slot.');
                    }

                    $remaining = OctoService::resolveRemainingSeats(
                        $targetSlot->id,
                        $affiliate->id,
                        (int) $affiliateLimit,
                        (int) $targetSlot->time_slot_seats,
                        $octoBooking->tour_package_id,
                    );

                    if ($octoBooking->tourists > $remaining) {
                        return OctoErrorResponse::unprocessableEntity('Not enough seats available on the requested time slot.');
                    }
                }
            }

            if (!empty($updates)) {
                $octoBooking->update($updates);
                $octoBooking->refresh();
            }

            if ($octoBooking->status === 'CONFIRMED') {
                $octoBooking->load('tourBooking');
            }

            $this->dispatchBookingNotifications($octoBooking);

            $pricingData = OctoService::hasCapability('pricing')
                ? $this->buildPricingForBooking($octoBooking, $this->resolveCurrency(), (float) ($affiliate->ota_affiliate_percentage ?? 0))
                : null;

            return response()->json(OctoService::formatBookingResponse($octoBooking, $pricingData['booking'] ?? null, $pricingData['perUnit'] ?? null));
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    /**
     * GET /bookings/{uuid}
     */
    public function getBooking(string $uuid): JsonResponse
    {
        try {
            $affiliate = $this->getAffiliateByReseller();
            if ($affiliate instanceof JsonResponse) return $affiliate;

            try {
                $octoBooking = OctoLegacyBookingBackfillService::resolve($uuid, $affiliate);
            } catch (RuntimeException $exception) {
                return OctoErrorResponse::unprocessableEntity($exception->getMessage());
            }

            if (!$octoBooking) {
                return OctoErrorResponse::invalidBookingUuid($uuid);
            }

            if ($octoBooking->isExpired()) {
                $octoBooking->update(['status' => 'EXPIRED']);
                $this->dispatchBookingNotifications($octoBooking->refresh());
            }

            if ($octoBooking->status === 'CONFIRMED') {
                $octoBooking->load('tourBooking');
            }

            $pricingData = OctoService::hasCapability('pricing')
                ? $this->buildPricingForBooking($octoBooking, $this->resolveCurrency(), (float) ($affiliate->ota_affiliate_percentage ?? 0))
                : null;
            return response()->json(OctoService::formatBookingResponse($octoBooking, $pricingData['booking'] ?? null, $pricingData['perUnit'] ?? null));
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    /**
     * POST /bookings/{uuid}/extend
     * Extends the expiration time of an ON_HOLD booking.
     */
    public function extendBooking(string $uuid): JsonResponse
    {
        try {
            $affiliate = $this->getAffiliateByReseller();
            if ($affiliate instanceof JsonResponse) return $affiliate;

            $octoBooking = OctoBooking::where('uuid', $uuid)->where('affiliate_id', $affiliate->id)->first();

            if (!$octoBooking) {
                return OctoErrorResponse::invalidBookingUuid($uuid);
            }

            if ($octoBooking->status === 'CONFIRMED') {
                return OctoErrorResponse::unprocessableEntity('Cannot extend a confirmed booking.');
            }

            if ($octoBooking->status === 'CANCELLED') {
                return OctoErrorResponse::bookingAlreadyCancelled($uuid);
            }

            if ($octoBooking->status !== 'ON_HOLD') {
                return OctoErrorResponse::unprocessableEntity("Cannot extend booking — current status is {$octoBooking->status}.");
            }

            if ($octoBooking->isExpired()) {
                $octoBooking->update(['status' => 'EXPIRED']);
                $this->dispatchBookingNotifications($octoBooking->refresh());
                return OctoErrorResponse::bookingExpired($uuid);
            }

            $expirationMinutes = max(1, (int) (request()->input('expirationMinutes') ?? 30));

            $octoBooking->update([
                'expire_at' => now()->addMinutes($expirationMinutes),
            ]);

            $octoBooking = $octoBooking->refresh();
            $pricingData = OctoService::hasCapability('pricing')
                ? $this->buildPricingForBooking($octoBooking, $this->resolveCurrency(), (float) ($affiliate->ota_affiliate_percentage ?? 0))
                : null;
            return response()->json(OctoService::formatBookingResponse($octoBooking, $pricingData['booking'] ?? null, $pricingData['perUnit'] ?? null));
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    /**
     * POST /bookings/{uuid}/cancel
     */
    public function cancelBooking(string $uuid): JsonResponse
    {
        try {
            $affiliate = $this->getAffiliateByReseller();
            if ($affiliate instanceof JsonResponse) return $affiliate;

            try {
                $octoBooking = OctoLegacyBookingBackfillService::resolve($uuid, $affiliate);
            } catch (RuntimeException $exception) {
                return OctoErrorResponse::unprocessableEntity($exception->getMessage());
            }

            if (!$octoBooking) {
                return OctoErrorResponse::invalidBookingUuid($uuid);
            }

            if ($octoBooking->status === 'CANCELLED') {
                return OctoErrorResponse::bookingAlreadyCancelled($uuid);
            }

            if (!in_array($octoBooking->status, ['ON_HOLD', 'CONFIRMED'], true)) {
                return OctoErrorResponse::unprocessableEntity("Booking cannot be cancelled — current status is {$octoBooking->status}.");
            }

            $reason = request()->input('reason') ?? '';

            // ON_HOLD — just release the hold, no tour_bookings_master record exists yet
            if ($octoBooking->status === 'ON_HOLD') {
                if ($octoBooking->isExpired()) {
                    $octoBooking->update(['status' => 'EXPIRED']);
                    $this->dispatchBookingNotifications($octoBooking->refresh());
                    return OctoErrorResponse::bookingExpired($uuid);
                }
                $octoBooking->update(['status' => 'CANCELLED']);
                $octoBooking     = $octoBooking->refresh();
                $this->dispatchBookingNotifications($octoBooking);
                $pricingData     = OctoService::hasCapability('pricing')
                    ? $this->buildPricingForBooking($octoBooking, $this->resolveCurrency(), (float) ($affiliate->ota_affiliate_percentage ?? 0))
                    : null;
                return response()->json(OctoService::formatBookingResponse($octoBooking, $pricingData['booking'] ?? null, $pricingData['perUnit'] ?? null, $reason, 'NONE'));
            }

            // CONFIRMED — cancel the underlying tour booking and issue refund history
            $tourBooking = TourBooking::find($octoBooking->tour_booking_id);

            if (!$tourBooking || $tourBooking->status === 'Cancelled') {
                $octoBooking->update(['status' => 'CANCELLED']);
                $octoBooking = $octoBooking->refresh();
                $this->dispatchBookingNotifications($octoBooking);
                $pricingData = OctoService::hasCapability('pricing')
                    ? $this->buildPricingForBooking($octoBooking, $this->resolveCurrency(), (float) ($affiliate->ota_affiliate_percentage ?? 0))
                    : null;
                return response()->json(OctoService::formatBookingResponse($octoBooking, $pricingData['booking'] ?? null, $pricingData['perUnit'] ?? null, $reason, 'NONE'));
            }

            $operator  = TourOperator::first();
            $timezone  = $operator?->timezone ?? 'UTC';
            $slot      = DB::table('tour_slot_times')->where('id', $octoBooking->tour_slot_id)->first();
            $slotStart = Carbon::createFromFormat('Y-m-d H:i:s', $slot->date . ' ' . $slot->time_from, $timezone);

            if ($slotStart->gt(Carbon::now($timezone))) {
                $cutoffDays = (int) ($operator?->cancellation_within_days ?? 0);
                if ($cutoffDays > 0 && Carbon::now($timezone)->gte($slotStart->copy()->subDays($cutoffDays))) {
                    return OctoErrorResponse::cancellationNotAllowed('The cancellation window has passed. Please contact the tour operator directly.');
                }
            }

            DB::beginTransaction();

            $refundResult = $this->cancelConfirmedOctoBooking(
                $octoBooking,
                $tourBooking,
                'Full Refund',
                $reason,
                (int) $tourBooking->customer_id,
                'Affiliate'
            );
            $refundAmount = $refundResult['refundAmount'];

            if ($tourBooking->tourists > 0) {
                TimeSlotAvailabilityService::updateSlotSummary($slot->id, -$tourBooking->tourists);
                $this->dispatchAvailabilityNotifications($octoBooking->affiliate_id, (string) $octoBooking->tour_package_id, $slot->date);
            }

            DB::commit();

            $octoBooking = $octoBooking->refresh();
            $this->dispatchBookingNotifications($octoBooking);
            $pricingData = OctoService::hasCapability('pricing')
                ? $this->buildPricingForBooking($octoBooking, $this->resolveCurrency(), (float) ($affiliate->ota_affiliate_percentage ?? 0))
                : null;
            $refundType = $refundAmount > 0 ? 'FULL' : 'NONE';
            return response()->json(OctoService::formatBookingResponse($octoBooking, $pricingData['booking'] ?? null, $pricingData['perUnit'] ?? null, $reason, $refundType));
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    /**
     * Cancels the underlying tour booking and issues refund history for a CONFIRMED OCTO booking.
     * Shared by the automated OCTO cancel endpoint and the manual dashboard cancel override for
     * bookings stuck within the reseller's own cancellation cutoff.
     *
     * Caller is responsible for the DB transaction boundary, the seat-summary decrement, and any
     * webhook/availability notifications — those differ between the automated and manual callers.
     *
     * @return array{refundAmount: float}
     */
    public function cancelConfirmedOctoBooking(
        OctoBooking $octoBooking,
        TourBooking $tourBooking,
        string $refundLabel,
        string $note,
        int $operatorId,
        string $operatorType
    ): array {
        // Resolve canonical title from constants aliases using the stable ota_id slug.
        $affiliate    = Customer::find($octoBooking->affiliate_id);
        $ota          = BokunUtils::initPrefix($affiliate?->ota_id ?? $affiliate?->name ?? 'octo');
        $resellerName = $ota['title'] ?? $affiliate?->name ?? 'Octo';
        $requestFrom  = strtoupper($resellerName);

        $completedAmount = (float) DB::table('transactions')
            ->where('tour_booking_id', $tourBooking->id)
            ->where('status', 'Completed')
            ->value('amount') ?? 0;

        $alreadyRefunded = (float) DB::table('transactions')
            ->where('tour_booking_id', $tourBooking->id)
            ->where('status', 'Refund')
            ->sum('amount');

        $refundAmount = max(0, $completedAmount - $alreadyRefunded);

        $refundTransactionId = DB::table('transactions')->insertGetId([
            'tour_booking_id'       => $tourBooking->id,
            'amount'                => $refundAmount,
            'fee_amount'            => 0,
            'via'                   => $resellerName,
            'transaction_number'    => null,
            'payment_type'          => 0,
            'response_init'         => '',
            'response_end'          => '',
            'response_end_datetime' => now(),
            'status'                => 'Refund',
            'visitor'               => request()->ip(),
            'request_from'          => $requestFrom,
            'created_by'            => $tourBooking->customer_id,
            'created_by_user_type'  => 'Affiliate',
            'created_at'            => now(),
        ]);

        DB::table('tour_booking_history')->insert([
            'tour_booking_id'        => $tourBooking->id,
            'transaction_id'         => $refundTransactionId,
            'tourists'               => $tourBooking->tourists + $tourBooking->infants,
            'operation'              => 'Cancelled',
            'ticket_cost'            => $refundAmount,
            'platform_fees'          => 0,
            'payment_type'           => $resellerName,
            'payment_method'         => 'Full Refund',
            'is_processed'           => 1,
            'details'                => json_encode([
                'amount'       => $refundAmount,
                'refund_type'  => $refundLabel,
                'payment_type' => $resellerName,
                'note'         => $note,
            ]),
            'operation_at'           => mysqlDT(),
            'operation_by'           => $operatorId,
            'operation_by_user_type' => $operatorType,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $tourBooking->update([
            'status'               => 'Cancelled',
            'updated_by'           => $operatorId,
            'updated_by_user_type' => $operatorType,
        ]);

        $octoBooking->update(['status' => 'CANCELLED']);

        return ['refundAmount' => $refundAmount];
    }

    /**
     * Resolves the effective TourPackageRate per slot for the pricing capability.
     *
     * Returns:
     *   [
     *     'by_date'      => [ 'Y-m-d' => Collection<standardRateId, effectiveRate>, ... ],
     *     'by_slot_time' => [ 'Y-m-d_HH:MM' => Collection<effectiveRateId, TourPackageRate>, ... ],
     *   ]
     *
     * In getAvailabilityCheck, look up by_slot_time[date_time_from] first (slot-specific
     * surcharge), then fall back to by_date[date] (general surcharge or standard rates).
     * Collection key = effective rate's own id (custom rate id on surcharge dates, standard rate id otherwise).
     *
     * Uses 4 queries regardless of range size:
     *   1. Standard rates
     *   2. All surcharge date windows overlapping the range (both apply_on_slots values)
     *   3. Custom rates for those windows
     *   4. rate_slot_mappings for slot-specific windows
     */
    private function resolvePricingByDate(int $packageId, int $affiliateId, string $startDate, string $endDate): array
    {
        // Derive the calendar years covered by the requested range.
        // A range spanning Dec–Jan needs rates for both years.
        $years = array_unique([(int) substr($startDate, 0, 4), (int) substr($endDate, 0, 4)]);

        // Standard rates grouped by scheduling_year so we can pick the right year per date
        $standardRatesByYear = TourPackageRate::where('tour_package_id', $packageId)
            ->where('affiliate_id', $affiliateId)
            ->whereIn('scheduling_year', $years)
            ->where('status', 'Active')
            ->whereNull('custom_date_id')
            ->get()
            ->groupBy('scheduling_year')
            ->map(fn ($rates) => $rates->keyBy('id'));

        $dates = $this->buildDateRange($startDate, $endDate);

        if ($standardRatesByYear->isEmpty()) {
            return ['by_date' => array_fill_keys($dates, collect()), 'by_slot_time' => []];
        }

        $ratesForDate = fn (string $date) => $standardRatesByYear->get((int) substr($date, 0, 4), collect());

        if (!hasSetting('CDR_PACKAGES') && !hasSetting('CDR_AFFILIATES')) {
            return ['by_date' => array_combine($dates, array_map($ratesForDate, $dates)), 'by_slot_time' => []];
        }

        // Load all surcharge windows overlapping this range (general and slot-specific)
        $surchargeDates = DB::table('surcharge_dates')
            ->where('affiliate_id', $affiliateId)
            ->where('tour_package_id', $packageId)
            ->where('date_range_start', '<=', $endDate)
            ->where('date_range_end', '>=', $startDate)
            ->orderBy('priority', 'desc')
            ->get();

        if ($surchargeDates->isEmpty()) {
            return ['by_date' => array_combine($dates, array_map($ratesForDate, $dates)), 'by_slot_time' => []];
        }

        // Custom rates grouped by custom_date_id → scheduling_year → keyed by rate's own id
        $surchargeIds   = $surchargeDates->pluck('id')->unique()->all();
        $allCustomRates = TourPackageRate::where('affiliate_id', $affiliateId)
            ->where('tour_package_id', $packageId)
            ->whereIn('custom_date_id', $surchargeIds)
            ->whereIn('scheduling_year', $years)
            ->where('status', 'Active')
            ->get()
            ->groupBy('custom_date_id')
            ->map(fn ($rates) => $rates->groupBy('scheduling_year')
                ->map(fn ($yearRates) => $yearRates->keyBy('id')));

        // time_from mappings for slot-specific surcharge dates (apply_on_slots = 1)
        $slotSurchargeIds = $surchargeDates->where('apply_on_slots', 1)->pluck('id')->all();
        $slotMappings     = [];
        if (!empty($slotSurchargeIds)) {
            foreach (DB::table('rate_slot_mappings')
                ->where('affiliate_id', $affiliateId)
                ->where('tour_package_id', $packageId)
                ->whereIn('custom_date_id', $slotSurchargeIds)
                ->get(['custom_date_id', 'time_from']) as $row) {
                $slotMappings[$row->custom_date_id][] = substr($row->time_from, 0, 5);
            }
        }

        $generalSurchargeDates = $surchargeDates->where('apply_on_slots', 0)->values();
        $slotSurchargeDates    = $surchargeDates->where('apply_on_slots', 1)->values();

        $byDate     = [];
        $bySlotTime = [];

        foreach ($dates as $date) {
            $year          = (int) substr($date, 0, 4);
            $standardRates = $standardRatesByYear->get($year, collect());

            // General surcharge: highest-priority window containing this date
            $generalMatch = null;
            foreach ($generalSurchargeDates as $sd) {
                if ($sd->date_range_start <= $date && $sd->date_range_end >= $date) {
                    $generalMatch = $sd;
                    break;
                }
            }
            // Direct lookup: use custom rates for this date's surcharge (keyed by rate id), or standard rates.
            $byDate[$date] = ($generalMatch && $allCustomRates->has($generalMatch->id))
                ? (($c = $allCustomRates->get($generalMatch->id)->get($year, collect())) && $c->isNotEmpty() ? $c : $standardRates)
                : $standardRates;

            // Slot-specific surcharge: build by_slot_time keys for each matching time_from.
            // Same rule as BookingService::getCustomRateDate(): a slot-specific window only
            // overrides the general one when its priority is at least as high.
            foreach ($slotSurchargeDates as $sd) {
                if ($sd->date_range_start > $date || $sd->date_range_end < $date) {
                    continue;
                }
                if ($generalMatch && $sd->priority < $generalMatch->priority) {
                    continue;
                }
                foreach ($slotMappings[$sd->id] ?? [] as $time) {
                    $key = $date . '_' . $time;
                    if (!isset($bySlotTime[$key])) {
                        $bySlotTime[$key] = ($allCustomRates->has($sd->id))
                            ? (($c = $allCustomRates->get($sd->id)->get($year, collect())) && $c->isNotEmpty() ? $c : $standardRates)
                            : $standardRates;
                    }
                }
            }
        }

        return ['by_date' => $byDate, 'by_slot_time' => $bySlotTime];
    }

    private function buildDateRange(string $startDate, string $endDate): array
    {
        $dates   = [];
        $current = Carbon::parse($startDate);
        $end     = Carbon::parse($endDate);
        while ($current->lte($end)) {
            $dates[] = $current->toDateString();
            $current->addDay();
        }
        return $dates;
    }

    // ── Webhooks / Notifications capability ───────────────────────────────────

    public function listWebhooks(): JsonResponse
    {
        try {
            if ($guard = $this->requireNotificationsCapability()) return $guard;

            $affiliate = $this->getAffiliateByReseller();
            if ($affiliate instanceof JsonResponse) return $affiliate;

            $webhooks = OctoWebhook::where('affiliate_id', $affiliate->id)->get();

            return response()->json($webhooks->map(fn (OctoWebhook $w) => $this->formatWebhook($w))->values());
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    public function createWebhook(): JsonResponse
    {
        try {
            if ($guard = $this->requireNotificationsCapability()) return $guard;

            $affiliate = $this->getAffiliateByReseller();
            if ($affiliate instanceof JsonResponse) return $affiliate;

            $url = request()->input('url');
            if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
                return OctoErrorResponse::badRequest('A valid webhook url is required.');
            }

            $notifications = request()->input('notificationTypes', []);
            if (!is_array($notifications) || empty($notifications)) {
                return OctoErrorResponse::badRequest('At least one notification type is required.');
            }

            $invalidNotifications = array_diff($notifications, self::VALID_NOTIFICATION_EVENTS);
            if (!empty($invalidNotifications)) {
                return OctoErrorResponse::badRequest(
                    'Invalid notification type(s): ' . implode(', ', $invalidNotifications) .
                    '. Allowed: ' . implode(', ', self::VALID_NOTIFICATION_EVENTS) . '.'
                );
            }

            $headers = request()->input('headers');
            if ($headers !== null && !is_array($headers)) {
                return OctoErrorResponse::badRequest('headers must be an object.');
            }

            $webhook = OctoWebhook::create([
                'uuid'         => (string) Str::uuid(),
                'affiliate_id' => $affiliate->id,
                'url'          => $url,
                'headers'      => $headers,
                'events'       => array_values($notifications),
                'is_active'    => true,
            ]);

            return response()->json($this->formatWebhook($webhook), 201);
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    public function getWebhook(string $id): JsonResponse
    {
        try {
            if ($guard = $this->requireNotificationsCapability()) return $guard;

            $affiliate = $this->getAffiliateByReseller();
            if ($affiliate instanceof JsonResponse) return $affiliate;

            $webhook = OctoWebhook::where('uuid', $id)->where('affiliate_id', $affiliate->id)->first();
            if (!$webhook) {
                return OctoErrorResponse::badRequest("Subscription '{$id}' not found.");
            }

            return response()->json($this->formatWebhook($webhook));
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    public function updateWebhook(string $id): JsonResponse
    {
        try {
            if ($guard = $this->requireNotificationsCapability()) return $guard;

            $affiliate = $this->getAffiliateByReseller();
            if ($affiliate instanceof JsonResponse) return $affiliate;

            $webhook = OctoWebhook::where('uuid', $id)->where('affiliate_id', $affiliate->id)->first();
            if (!$webhook) {
                return OctoErrorResponse::badRequest("Subscription '{$id}' not found.");
            }

            $updates = [];

            if (request()->has('url')) {
                $url = request()->input('url');
                if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
                    return OctoErrorResponse::badRequest('A valid webhook url is required.');
                }
                $updates['url'] = $url;
            }

            if (request()->has('headers')) {
                $headers = request()->input('headers');
                if ($headers !== null && !is_array($headers)) {
                    return OctoErrorResponse::badRequest('headers must be an object.');
                }
                $updates['headers'] = $headers;
            }

            if (request()->has('notificationTypes')) {
                $notifications = request()->input('notificationTypes');
                if (!is_array($notifications) || empty($notifications)) {
                    return OctoErrorResponse::badRequest('At least one notification type is required.');
                }
                $invalidNotifications = array_diff($notifications, self::VALID_NOTIFICATION_EVENTS);
                if (!empty($invalidNotifications)) {
                    return OctoErrorResponse::badRequest(
                        'Invalid notification type(s): ' . implode(', ', $invalidNotifications) .
                        '. Allowed: ' . implode(', ', self::VALID_NOTIFICATION_EVENTS) . '.'
                    );
                }
                $updates['events'] = array_values($notifications);
            }

            if (request()->has('active')) {
                $updates['is_active'] = (bool) request()->input('active');
            }

            if (!empty($updates)) {
                $webhook->update($updates);
            }

            return response()->json($this->formatWebhook($webhook->fresh()));
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    public function deleteWebhook(string $id): JsonResponse
    {
        try {
            if ($guard = $this->requireNotificationsCapability()) return $guard;

            $affiliate = $this->getAffiliateByReseller();
            if ($affiliate instanceof JsonResponse) return $affiliate;

            $webhook = OctoWebhook::where('uuid', $id)->where('affiliate_id', $affiliate->id)->first();
            if (!$webhook) {
                return OctoErrorResponse::badRequest("Subscription '{$id}' not found.");
            }

            $webhook->delete();

            return response()->json(null, 204);
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    public function pingWebhook(string $id): JsonResponse
    {
        try {
            if ($guard = $this->requireNotificationsCapability()) return $guard;

            $affiliate = $this->getAffiliateByReseller();
            if ($affiliate instanceof JsonResponse) return $affiliate;

            $webhook = OctoWebhook::where('uuid', $id)->where('affiliate_id', $affiliate->id)->first();
            if (!$webhook) {
                return OctoErrorResponse::badRequest("Subscription '{$id}' not found.");
            }

            SendOctoWebhookJob::dispatch(
                $webhook->id,
                $webhook->url,
                $webhook->headers ?? [],
                ['type' => 'ping', 'message' => 'This is a test ping from the OCTO API.'],
            );

            return response()->json($this->formatWebhook($webhook));
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    /**
     * Dispatches booking webhook notifications to all active webhooks registered
     * by the booking's affiliate. Called after any supplier-side booking status change.
     */
    public function dispatchBookingNotifications(OctoBooking $octoBooking): void
    {
        $webhooks = OctoWebhook::where('affiliate_id', $octoBooking->affiliate_id)
            ->where('is_active', true)
            ->whereJsonContains('events', 'BOOKING_UPDATE')
            ->get();

        if ($webhooks->isEmpty()) {
            return;
        }

        foreach ($webhooks as $webhook) {
            SendOctoWebhookJob::dispatch(
                $webhook->id,
                $webhook->url,
                $webhook->headers ?? [],
                [
                    'id'               => (string) Str::uuid(),
                    'subscriptionId'   => $webhook->uuid,
                    'notificationType' => 'BOOKING_UPDATE',
                    'utcCreatedAt'     => now()->utc()->toIso8601String(),
                    'data'             => ['uuid' => $octoBooking->uuid],
                ],
            );
        }
    }

    public function dispatchProductNotifications(int $packageId): void
    {
        $affiliateIds = PackageAffiliateAssignment::where('package_id', $packageId)
            ->pluck('affiliate_id');

        if ($affiliateIds->isEmpty()) {
            return;
        }

        $webhooks = OctoWebhook::whereIn('affiliate_id', $affiliateIds)
            ->where('is_active', true)
            ->whereJsonContains('events', 'PRODUCT_UPDATE')
            ->get();

        if ($webhooks->isEmpty()) {
            return;
        }

        foreach ($webhooks as $webhook) {
            SendOctoWebhookJob::dispatch(
                $webhook->id,
                $webhook->url,
                $webhook->headers ?? [],
                [
                    'id'               => (string) Str::uuid(),
                    'subscriptionId'   => $webhook->uuid,
                    'notificationType' => 'PRODUCT_UPDATE',
                    'utcCreatedAt'     => now()->utc()->toIso8601String(),
                    'data'             => ['productId' => (string) $packageId],
                ],
            );
        }
    }

    public function dispatchAvailabilityNotifications(int $affiliateId, string $productId, string $localDate): void
    {
        $webhooks = OctoWebhook::where('affiliate_id', $affiliateId)
            ->where('is_active', true)
            ->whereJsonContains('events', 'AVAILABILITY_UPDATE')
            ->get();

        if ($webhooks->isEmpty()) {
            return;
        }

        foreach ($webhooks as $webhook) {
            SendOctoWebhookJob::dispatch(
                $webhook->id,
                $webhook->url,
                $webhook->headers ?? [],
                [
                    'id'               => (string) Str::uuid(),
                    'subscriptionId'   => $webhook->uuid,
                    'notificationType' => 'AVAILABILITY_UPDATE',
                    'utcCreatedAt'     => now()->utc()->toIso8601String(),
                    'data'             => [
                        'productId'      => $productId,
                        'optionId'       => 'default',
                        'localDateStart' => $localDate,
                        'localDateEnd'   => $localDate,
                    ],
                ],
            );
        }
    }

    private function requireNotificationsCapability(): ?JsonResponse
    {
        if (!in_array('notifications', request()->input('octo_capabilities', []))) {
            return OctoErrorResponse::badRequest('The notifications capability is required.');
        }
        return null;
    }

    private function formatWebhook(OctoWebhook $webhook): array
    {
        return [
            'id'      => $webhook->uuid,
            'url'     => $webhook->url,
            'headers' => $webhook->headers ?? [],
            'notificationTypes' => $webhook->events,
            'active'  => $webhook->is_active,
        ];
    }

    private function s3Url(string $path): string
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('s3');

        return $disk->url($path);
    }

    /**
     * Returns true when the request carries meaningful contact data to act on:
     * either an explicit booking-level contact key, or a first unitItem contact
     * with at least one non-null field.
     */
    private function hasEffectiveContactUpdate(OctoBooking $octoBooking): bool
    {
        if (request()->has('contact')) {
            return true;
        }

        if (request()->has('unitItems')) {
            $firstContact = request()->input('unitItems.0.contact');
            return $firstContact !== null && !empty(array_filter((array) $firstContact));
        }

        return false;
    }

    /**
     * Resolve the effective contact for a booking update.
     *
     * Priority: booking-level contact (if non-null) → first unitItem's contact
     * (only if that contact has at least one non-null field).
     */
    private function resolveEffectiveContactForUpdate(OctoBooking $octoBooking): ?array
    {
        if (request()->has('contact') && request()->input('contact') !== null) {
            return request()->input('contact');
        }

        // Fall back to first unitItem contact from the request, then from stored unit_items
        $unitItems   = request()->input('unitItems') ?? ($octoBooking->unit_items ?? []);
        $unitContact = !empty($unitItems) ? ($unitItems[0]['contact'] ?? null) : null;

        // Ignore unit contacts where every field is null (only the lead guest has real data)
        return ($unitContact && array_filter((array) $unitContact)) ? $unitContact : null;
    }
}
