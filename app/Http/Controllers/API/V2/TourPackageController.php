<?php

namespace App\Http\Controllers\API\V2;

use App\Constants\Constants;
use App\Http\Controllers\API\V2\BaseController;
use App\Models\Customer;
use App\Repositories\Addon\AddonFormRepository;
use Illuminate\Support\Facades\DB;
use App\Repositories\TourPackage\TourPackageRepository;
use Exception;

class TourPackageController extends BaseController
{
    /**
     * Get the complete package details
     *
     * @param mixed $year
     * @param mixed $packageId
     * @param mixed $affiliateId
     */
    public function tourOperatorLogo($tourOperatorId)
    {
        $tourOperatorLogo = DB::table('tour_operators as to')
            ->select(
                'to.logo as TourOperatorLogo',
                'to.tenant_id',
                'to.timezone',
                'to.name'
            )
            ->first();

        // Uncomment this line to get the logo
        $tourOperatorLogo->TourOperatorLogo = tourOperatorLogo($tourOperatorLogo->tenant_id, $tourOperatorLogo->TourOperatorLogo);

        return $tourOperatorLogo;
    }

    /**
     * Get the complete package details
     *
     * @param mixed $date
     * @param int $tourOperatorId
     * @param int $packageId
     * @param int $affiliateId
     * @param mixed $comboPackageIds
     * @param int $withRateGroups
     */
    public function getPackages($date, $tourOperatorId, $packageId = 0, $affiliateId = 0, $comboPackageIds = 0, $withRateGroups = 0, $slotTimeId = 0)
    {
        $date = date('Y-m-d', strtotime($date));
        $year = date('Y', strtotime($date));

        $selectFields = [
            'tp.tour_operator_id',
            'tp.id as package_id',
            'tp.name as package_name',
            'tp.short_description',
            'tp.long_description',
            'tp.hours',
            'tp.minutes',
            'tp.things_to_bring',
            'tp.service_commission_percentage',
            'tp.stripe_commission_percentage',
            'tp.latitude',
            'tp.longitude',
            'tp.travel_duration',
            'tp.category_id',
            'tp.type',
            'tp.is_group_rate_enabled',
            'tp.is_partner_package',
            'tp.disable_slot_overlap_blocking',
            'tp.package_has_slots',
            'tp.package_has_waiver',
            'tp.package_has_permit',
            'tp.tax_applicable',
            'tp.slot_close_on_booking',
            'tp.show_seat_availability',
            'tp.frontend_enabled',
            'tp.block_ctb_duration',
            'tp.ctb_description',
            'tp.has_contacts',
            'to.tenant_id',
            'to.name as company_name',
            'to.phone_number',
            'to.logo',
            'st.permit_fee',
            'ci.state_id',
            'to.timezone',
            'ct.currency',
            'ct.code as country_code'
        ];

        $query = DB::table('countries as ct')
            ->join('states as st', 'st.country_id', '=', 'ct.id')
            ->join('cities as ci', 'ci.state_id', '=', 'st.id')
            ->join('tour_operators as to', 'to.city_id', '=', 'ci.id')
            ->join('tour_packages as tp', 'tp.tour_operator_id', '=', 'to.id');

        // if ($comboPackageIds) {
        //     $query = $query->join('tour_slot_dates as tsd', 'tsd.tour_package_id', '=', 'tp.id');
        // }

        $query = $query->where('to.id', $tourOperatorId)
            ->when($packageId != 0 && $comboPackageIds == 0, fn($query) => $query->where('tp.id', '=', $packageId))
            ->when($packageId == 0 && $comboPackageIds != 0, fn($query) => $query->whereIn('tp.id', explode(',', $comboPackageIds)))
            ->when($packageId == 0 && $comboPackageIds == 0, fn($query) => $query->where('tp.is_combo_package', '=', 0))
            ->where('tp.frontend_enabled', '=', 1)
            ->where('tp.deleted_by', '=', 0)
            ->where('ci.status', '=', "Active")
            ->where('st.status', '=', "Active")
            ->where('ct.status', '=', "Active")
            ->where('to.status', '=', "Active")
            ->where('tp.status', '=', "Active");

        if ($comboPackageIds) {
            $query = $query
                // ->whereDate('tsd.date', '=', $date)
                // ->where('tsd.status', '=', "Active")
                // ->where('tsd.deleted_by', '=', 0)
                ->where('tp.is_combo_package', '=', 1);
        }

        $tourPackageData = $query->select($selectFields)
            ->orderBy('tp.Order_by')
            ->get();

        if ($affiliateId) {
            $customer = Customer::find($affiliateId);
        }

        $tourPackageRateGroups = [];
        if (!$tourPackageData->isEmpty()) {
            $minMaxPaxApply = in_array(minMaxPaxApply(), [Constants::STATUS_ONE, Constants::STATUS_THREE]);

            foreach ($tourPackageData as $data) {
                $isBlackoutDate = isBlackoutDateEnabled($date);
                $data->affiliate_processing_percentage = $affiliateId
                    ? ($isBlackoutDate ? $data->service_commission_percentage : $customer->affiliate_processing_percentage)
                    : 0;

                $data->stripe_affiliate_percentage = $affiliateId
                    ? ($isBlackoutDate ? $data->stripe_commission_percentage : $customer->stripe_affiliate_percentage)
                    : 0;
                if (getNextYearCommissionPercentage($date)) {
                    if ($affiliateId) {
                        if ($isBlackoutDate) {
                            $serviceCommission = getNormalCommissionPercentage($data->package_id);
                            $data->affiliate_processing_percentage = $serviceCommission['service_commission_percentage'];
                            $data->stripe_affiliate_percentage = $serviceCommission['stripe_commission_percentage'];
                        } else {
                            $affiliateCommission = getAffiliateCommissionPercentage($affiliateId);
                            $data->affiliate_processing_percentage = $affiliateCommission['affiliate_processing_percentage'];
                            $data->stripe_affiliate_percentage = $affiliateCommission['stripe_affiliate_percentage'];
                        }
                    } else {
                        $serviceCommission = getNormalCommissionPercentage($data->package_id);
                        $data->service_commission_percentage = $serviceCommission['service_commission_percentage'];
                        $data->stripe_commission_percentage = $serviceCommission['stripe_commission_percentage'];
                    }
                }
                $data->type = config('constants.categories')[$data->type];

                $data->photos = DB::table('tour_package_photos')
                    ->join('tour_packages', 'tour_package_photos.tour_package_id', '=', 'tour_packages.id')
                    ->select('tour_packages.id', 'tour_packages.tour_operator_id', 'tour_package_photos.placement', 'tour_package_photos.path')
                    ->where('tour_package_photos.tour_package_id', $data->package_id)
                    ->where('tour_package_photos.deleted_by', 0)
                    ->orderBy('placement')
                    ->get();

                $placementMapping = [
                    "1" => 'HeaderImage',
                    "2" => 'DescriptionImage',
                    "3" => 'HeaderOne',
                    "4" => 'HeaderTwo',
                    "5" => 'PaymentPageDateTimeSectionImage',
                    "6" => 'FrontendPackageImage',
                    "7" => 'EmailTemplateImage'
                ];

                foreach ($data->photos as $val => $image) {
                    $placementValue = $data->photos[$val]->placement;

                    if (isset($placementMapping[$placementValue])) {
                        $placement = $placementMapping[$placementValue];

                        $data->$placement = tourPackageLogo($data->tenant_id, $data->package_id, $image->path);
                        $data->photos[$val] = $data->$placement;
                    }
                }

                $duration = '';
                // if ($data->category_id == 1) {
                if ($data->hours || $data->minutes) {
                    if ($data->hours) {
                        $duration .= "{$data->hours} Hour";

                        if ($data->minutes) {
                            $duration .= " and {$data->minutes} Minutes";
                        }
                    } elseif ($data->minutes) {
                        $duration .= "{$data->minutes} Minutes";
                    }
                }

                $data->duration = $duration;

                $thingsToBring = json_decode($data->things_to_bring, true);

                if ($withRateGroups) {
                    $affiliateIdForRate = $affiliateId && !$isBlackoutDate ? $affiliateId : NULL;
                    $customRateDate = isCustomDateRateEnabled($date, $data->package_id, $affiliateIdForRate, $slotTimeId);

                    $tourPackageRates = DB::table('tour_package_rates as pr')
                        ->where('pr.tour_package_id', $data->package_id)
                        ->where('pr.status', '=', "Active")
                        ->where('pr.deleted_by', '=', 0)
                        ->when($affiliateIdForRate != 0, fn($query) => $query->where('pr.affiliate_id', $affiliateId), fn($query) => $query->whereNull('pr.affiliate_id'))
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
                            'pr.is_infant_rate',
                            'pr.is_group_rate',
                            'pr.min_group_size',
                            'pr.max_group_size'
                        )
                        ->orderBy('pr.orderby')
                        ->get();

                    if ($tourPackageRates->count() > 0) {
                        foreach ($tourPackageRates as $package => $packageRates) {
                            $tourPackageRates[$package]->processing_fee = number_format((float)$tourPackageRates[$package]->processing_charge_percentage, 2, '.', '');
                            $tourPackageRates[$package]->permit_fee = number_format((float)$tourPackageRates[$package]->permit_fee, 2, '.', '');
                            $tourPackageRates[$package]->tax = number_format((float)$tourPackageRates[$package]->additional_charge, 2, '.', '');
                            $tourPackageRates[$package]->min_pax_allowed = $minMaxPaxApply ? $tourPackageRates[$package]->min_pax_allowed : 0;
                            $tourPackageRates[$package]->max_pax_allowed = $minMaxPaxApply ? $tourPackageRates[$package]->max_pax_allowed : 0;

                            if ($data->is_group_rate_enabled) {
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

                        $tourPackageRateGroups[$packageRates->tour_package_id] = $data->is_group_rate_enabled ? $tourPackageRateGroups : $tourPackageRates;
                    }
                }

                $data->logo = tourOperatorLogo($data->tenant_id, $data->logo);

                unset($data->hours, $data->minutes, $data->things_to_bring, $data->photos, $data->state_id, $data->permit_fee, $data->category_id);
            }

            $hasCustomFields = 0;
            $hotels = [];
            if ($packageId) {
                $hotels = DB::table('hotels')
                    ->select('id', 'name', 'address', 'email', 'phone_number', 'image')
                    ->where('tour_operator_id', $tourOperatorId)
                    ->where('tour_package_id', $packageId)
                    ->where('deleted_by', 0)
                    ->get();

                foreach ($hotels as $hotel) {
                    $hotel->image = hotelImage($data->tenant_id, $packageId, $hotel->id, $hotel->image);
                }

                $hasCustomFields = DB::table('addon_forms as afo')
                    ->join('addon_fields as afi', 'afo.id', '=', 'afi.form_id')
                    ->where('afo.tour_package_id', $packageId)
                    ->whereNull('afi.deleted_at')
                    ->where('afo.status', 'Active')
                    ->where('afi.status', 'Active')
                    ->count();
            }

            // Filter only active checkout terms
            $checkoutTerms = tourOperator()->custom_checkout_terms ?? [];
            $activeCheckoutTerms = array_values(array_filter($checkoutTerms, function($term) {
                return $term['status'] === 'active';
            }));

            return response()->json([
                'tourPackageData' => $tourPackageData,
                'thingsToBring' => $thingsToBring,
                'tourPackageRateGroups' => $tourPackageRateGroups,
                'hasCustomFields' => $hasCustomFields > 0 ? true : false,
                'hotels' => $hotels,
                'callToBook' => hasSetting('CALL_TO_BOOK'),
                'customCheckoutTerms' => $activeCheckoutTerms
            ]);
        } else {
            return $this->sendError('Tour Package Not Found.');
        }
    }

    /**
     * get a custom form fields for a tour package checkout options
     * @param string|int $id tour package id
     * @return mixed
     */
    public function customForm($id)
    {
        try {
            $tourRepo = app(TourPackageRepository::class);
            $form = $tourRepo->getCustomForm($id);
            return response()->json(["form" => $form, "isset" => isset($form), "error" => null]);
        } catch (Exception $e) {
            return response()->json(["form" => null, "isset" => false, "error" => $e->getMessage()]);
        }
    }

    public function getAddonForm($id, $builder = 0)
    {
        try {
            $addonRepo = new AddonFormRepository();
            $form = $addonRepo->getPackageAddonForm($id, $builder, forFrontend: true);
            return $form;
        } catch (Exception $e) {
            return response()->json(["form" => null, "isset" => false, "error" => $e->getMessage()]);
        }
    }
}
