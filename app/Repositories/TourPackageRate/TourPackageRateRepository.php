<?php

namespace App\Repositories\TourPackageRate;

use App\Exceptions\Core\GeneralCoreException;
use App\Models\Admin;
use App\Models\BookingTransact;
use App\Models\Customer;
use App\Models\GlobalRateSchedule;
use App\Models\RateSlotMapping;
use App\Models\Staff;
use App\Models\SurchargeDate;
use App\Models\Tax;
use App\Models\TourPackage;
use App\Models\TourPackageRate;
use App\Models\TourSlotTime;
use App\Repositories\Octo\OctoRepository;
use App\Repositories\Repository;
use App\Services\Cart\CartService;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TourPackageRateRepository extends Repository
{
    /**
     * @var string
     */
    public $tourPackage = TourPackage::class;

    /**
     * @var string
     */
    public $tourPackageRate = TourPackageRate::class;

    /**
     * @var string
     */
    public $affiliate = Customer::class;

    /**
     * @var string
     */
    public $slotTime = TourSlotTime::class;

    /**
     * @var string
     */
    public $bookingTransact = BookingTransact::class;

    /**
     * @var string
     */
    public $standardRateDate = SurchargeDate::class;

    /**
     * @var string
     */
    public $rateSlotMapping = RateSlotMapping::class;

    /**
     * @var string
     */
    public $tax = Tax::class;

    /**
     * @var string
     */
    public $globalRateSchedule = GlobalRateSchedule::class;

    /**
     * @var string
     */
    public $admin = Admin::class;

    /**
     * @var string
     */
    public $staff = Staff::class;

    /**
     * Get the packages.
     *
     * @param int $id
     * @return mixed
     */
    public function getDataForFilter()
    {
        try {
            $packages = $this->tourPackage::active()->departmentPackage()->orderBy('Order_by')->get();
            $affiliates = $this->affiliate::IsAffiliate()->departmentPackage()->get();
            $taxPercent = $this->tax::active()->first();

            $standardRateDates = $this->standardRateDate::departmentPackage()->whereDate('date_range_end', '>', date('Y-m-d'))->orderBy('date_range_start')->get();

            foreach ($standardRateDates as $standardRateDate) {
                $standardRateDate->created_by_with_name = $this->getCreatedByUserName($standardRateDate);
            }

            $pastStandardRateDates = $this->standardRateDate::departmentPackage()->whereDate('date_range_end', '<=', date('Y-m-d'))->orderBy('date_range_start')->get();

            foreach ($pastStandardRateDates as $pastStandardRateDate) {
                $pastStandardRateDate->created_by_with_name = $this->getCreatedByUserName($pastStandardRateDate);
            }
            $cashPriceEnabled = cashPriceEnabled();

            return [
                'standardRateDates' => $standardRateDates,
                'pastStandardRateDates' => $pastStandardRateDates,
                'packages' => $packages,
                'affiliates' => $affiliates,
                'taxPercent' => $taxPercent,
                'cashPriceEnabled' => $cashPriceEnabled
            ];
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Get the package rates.
     *
     * @param array $data
     * @return mixed
     */
    public function getPackageRates($data)
    {
        try {
            $package = $this->tourPackage::find($data['tour_package_id']);
            
            $rates = $this->tourPackageRate::where('scheduling_year', $data['scheduling_year'])
                ->where('tour_package_id', $data['tour_package_id'])
                ->when(isset($data['affiliate_id']) && $data['affiliate_id'], function ($query) use ($data) {
                    return match ($data['affiliate_id']) {
                        'all' => $query->whereNotNull('affiliate_id'),
                        default => $query->where('affiliate_id', $data['affiliate_id']),
                    };
                }, function ($query) {
                    return $query->whereNull('affiliate_id');
                })
                ->when(isset($data['custom_date_id']) && $data['custom_date_id'], function ($query) use ($data) {
                    return $query->where('custom_date_id', $data['custom_date_id']);
                }, function ($query) {
                    return $query->whereNull('custom_date_id');
                })
                ->when(isset($data['global_rate']) && $data['global_rate'], function ($query) use ($data) {
                    return $query->where('global_rate', $data['global_rate']);
                })
                ->orderBy('orderby')
                ->get();

            $slots = [];
            if (isset($data['custom_date_id']) && $data['custom_date_id']) {
                $standardRateDate = $this->standardRateDate::find($data['custom_date_id']);
                if ($standardRateDate->apply_on_slots) {
                    $slots = $this->slotTime::where('tour_package_id', $data['tour_package_id'])
                        ->whereBetween('date', [$standardRateDate->date_range_start, $standardRateDate->date_range_end])
                        ->groupBy('time_from')  // Ensure unique 'time_from' values
                        ->orderBy('time_from', 'ASC')
                        ->get('time_from');

                    if ($slots->count()) {
                        foreach ($slots as $slot) {
                            $rateSlotMapping = $this->rateSlotMapping::where('tour_package_id', $data['tour_package_id'])
                                ->where('custom_date_id', $data['custom_date_id'])
                                ->where('time_from', $slot->time_from)
                                ->count();

                            $slot->selected = $rateSlotMapping ? true : false;
                        }
                    }
                }
            }

            // Set the date to fetch the year wise fees percentage
            $formattedDate = ($data['scheduling_year'] ?: date('Y')) . '-' . date('m-d');
            $natFeesPercentage = $package->service_commission_percentage;
            if (isset($data['affiliate_id']) && $data['affiliate_id'] && $data['affiliate_id'] !== 'all') {
                $affiliate = $this->affiliate::find($data['affiliate_id']);
                $natFeesPercentage = $affiliate->affiliate_processing_percentage ?? 0;

                if (getNextYearCommissionPercentage($formattedDate)) {
                    $affiliateCommission = getAffiliateCommissionPercentage($affiliate->id);
                    $natFeesPercentage = $affiliateCommission['affiliate_processing_percentage'];
                }
            } elseif (getNextYearCommissionPercentage($formattedDate)) {
                $serviceCommission = getNormalCommissionPercentage($package->id);
                $natFeesPercentage = $serviceCommission['service_commission_percentage'];
            }

            foreach ($rates as $rate) {
                $rate->created_by_with_name = $this->getCreatedByUserName($rate);
            }

            return [
                'rates' => $rates,
                'slots' => $slots,
                'nat_fees_percentage' => $natFeesPercentage,
                'is_group_rate_enabled' => $package->is_group_rate_enabled,
                'is_partner_package' => $package->is_partner_package
            ];
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Save the package rates.
     *
     * @param array $data
     * @return mixed
     */
    public function savePackageRates($data)
    {
        try {
            $cashPriceEnabled = cashPriceEnabled();
            $package = $this->tourPackage::find($data['rateGroup']['tour_package_id']);

            $rateGroupData = $data['rateGroup'];

            $query = $this->tourPackageRate::where('tour_package_id', $rateGroupData['tour_package_id'])
                ->where('scheduling_year', $rateGroupData['scheduling_year'])
                ->when(isset($rateGroupData['affiliate_id']) && $rateGroupData['affiliate_id'], function ($query) use ($rateGroupData) {
                    return $query->where('affiliate_id', $rateGroupData['affiliate_id']);
                }, function ($query) {
                    return $query->whereNull('affiliate_id');
                })
                ->whereNull('custom_date_id')
                ->where('mapping_key', $rateGroupData['mapping_key']);

            if (isset($rateGroupData['id']) && !empty($rateGroupData['id'])) {
                $query->where('id', '!=', $rateGroupData['id']);
            }

            $mappingKeyRateGroupExist = $query->count();

            if ($rateGroupData['mapping_key'] != 0 && $mappingKeyRateGroupExist > 0) {
                throw new Exception("Mapping key has been already taken.");
            }

            // Check if the rate group exists (for updating) or create a new one
            $rateGroup = isset($rateGroupData['id']) && !empty($rateGroupData['id'])
                ? $this->tourPackageRate::find($rateGroupData['id'])
                : new $this->tourPackageRate;

            if ($rateGroup) {
                $rateGroup->scheduling_year = $rateGroupData['scheduling_year'];
                $rateGroup->tour_package_id = $rateGroupData['tour_package_id'];
                $rateGroup->rate_for = $rateGroupData['rate_for'];
                $rateGroup->age_from = $rateGroupData['age_from'];
                $rateGroup->age_to = $rateGroupData['age_to'];
                $rateGroup->rate = $rateGroupData['rate'];
                $rateGroup->permit_fee = $rateGroupData['permit_fee'];
                $rateGroup->additional_charge = $rateGroupData['additional_charge'];
                $rateGroup->min_pax_allowed = $rateGroupData['min_pax_allowed'];
                $rateGroup->max_pax_allowed = $rateGroupData['max_pax_allowed'];
                $rateGroup->description = $rateGroupData['description'];
                $rateGroup->orderby = $rateGroupData['orderby'];
                $rateGroup->mapping_key = $rateGroupData['mapping_key'];
                $rateGroup->is_infant_rate = $rateGroupData['is_infant_rate'];
                $rateGroup->is_cash_rate = $rateGroupData['is_cash_rate'] ?? 0;
                if($cashPriceEnabled == 1 && $rateGroup->is_cash_rate == 1) {
                    $rateGroup->cash_rate = $rateGroupData['cash_rate'];
                    $rateGroup->cash_tax  = $rateGroupData['cash_tax'];
                }
                $rateGroup->global_rate = $rateGroupData['global_rate'];
                $rateGroup->approval = $rateGroupData['approval'];
                $rateGroup->status = $rateGroupData['status'];

                if (isset($rateGroupData['affiliate_id']) && $rateGroupData['affiliate_id']) {
                    $rateGroup->affiliate_id = $rateGroupData['affiliate_id'];
                }

                if ($package->is_group_rate_enabled) {
                    $rateGroup->is_group_rate = 1;
                    $rateGroup->min_group_size = $rateGroupData['min_group_size'];
                    $rateGroup->max_group_size = $rateGroupData['max_group_size'];
                }

                if ($package->is_partner_package) {
                    if($cashPriceEnabled == 1 && $rateGroup->is_cash_rate == 1) {
                        $subTotal = $package->is_group_rate_enabled ? $rateGroup->cash_rate + $rateGroup->cash_tax : $rateGroup->cash_rate + $rateGroup->cash_tax + $rateGroup->permit_fee;
                    } else {
                        $subTotal = $package->is_group_rate_enabled ? $rateGroup->rate + $rateGroup->additional_charge : $rateGroup->rate + $rateGroup->additional_charge + $rateGroup->permit_fee;
                    }
                    if ($rateGroupData['partner_fee_type'] == 'Percentage') {
                        $partnerFees = roundout(($subTotal * $rateGroupData['partner_fee_value']) / 100, 2);
                        $percentage = $rateGroupData['partner_fee_value'];
                    } else {
                        $partnerFees = $rateGroupData['partner_fee_value'];
                        $percentage = ($subTotal > 0) ? roundout(($partnerFees / $subTotal) * 100, 2) : 0.00;
                    }
                    $rateGroup->partner_fee_type = $rateGroupData['partner_fee_type'];
                    $rateGroup->partner_fee_percentage = $percentage;
                    $rateGroup->partner_fee_amount = $partnerFees;
                }

                if (isset($rateGroupData['id']) && !empty($rateGroupData['id'])) {
                    $rateGroup->updated_by = Auth::id();
                    $rateGroup->updated_by_user_type = userType();
                } else {
                    $rateGroup->created_by = Auth::id();
                    $rateGroup->created_by_user_type = userType();
                }

                $rateGroup->save();
                app(OctoRepository::class)->dispatchProductNotifications($rateGroup->tour_package_id);

                return [
                    'status' => 'success',
                    'data' => [
                        'id' => $rateGroup->id
                    ]
                ];
            }

            return [
                'status' => 'error',
                'message' => 'Rate group could not be saved.'
            ];
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Summary of saveCustomDateRates
     * @param mixed $data
     * @return mixed
     */
    public function saveCustomDateRates($data)
    {
        DB::beginTransaction();
        try {
            // Split the date range into two separate dates
            if (isset($data['date'])) {
                [$date_range_start, $date_range_end] = explode(' - ', $data['date']);

                // Add these to the request payload or process as needed
                $data['date_range_start'] = date('Y-m-d', strtotime($date_range_start));
                $data['date_range_end'] = date('Y-m-d', strtotime($date_range_end));
            }

            // Check for any overlapping date ranges
            $isOverlapping = $this->standardRateDate::where(function ($query) use ($data) {
                $query->whereBetween('date_range_start', [$data['date_range_start'], $data['date_range_end']])
                    ->orWhereBetween('date_range_end', [$data['date_range_start'], $data['date_range_end']])
                    ->orWhere(function ($query) use ($data) {
                        $query->where('date_range_start', '<=', $data['date_range_start'])
                            ->where('date_range_end', '>=', $data['date_range_end']);
                    });
            })
                ->where('tour_package_id', $data['tour_package_id'])
                ->when(isset($data['affiliate_id']) && $data['affiliate_id'] != 0, function ($query) use ($data) {
                    return $query->where('affiliate_id', $data['affiliate_id']);
                }, function ($query) {
                    return $query->whereNull('affiliate_id');
                })
                ->when(isset($data['custom_date_id']) && $data['custom_date_id'], function ($query) use ($data) {
                    return $query->where('id', '!=', $data['custom_date_id']);
                })
                ->get();
        
            $standardRateDate = isset($data['custom_date_id']) && $data['custom_date_id']
                ? $this->standardRateDate::find($data['custom_date_id'])
                : new $this->standardRateDate;

            if ($standardRateDate) {
                $standardRateDate->custom_date_name = $data['custom_date_name'];
                $standardRateDate->tour_package_id = $data['tour_package_id'];
                $standardRateDate->affiliate_id = ($data['affiliate_id'] != 0) ? $data['affiliate_id'] : null;
                $standardRateDate->date = $data['date_range_start'];
                $standardRateDate->date_range_start = $data['date_range_start'];
                $standardRateDate->date_range_end = $data['date_range_end'];
                $standardRateDate->apply_on_slots = $data['apply_on_slots'];

                if ($data['custom_date_id'] == 0) {
                    $standardRateDate->priority = $isOverlapping->count() + 1;
                }

                if (isset($data['custom_date_id']) && $data['custom_date_id']) {
                    $standardRateDate->updated_by = Auth::id();
                    $standardRateDate->updated_by_user_type = userType();
                } else {
                    $standardRateDate->created_by = Auth::id();
                    $standardRateDate->created_by_user_type = userType();
                }

                if ($standardRateDate->save()) {
                    if (count($data['rate_groups'])) {
                        foreach ($data['rate_groups'] as $rateGroupData) {
                            $rateGroupData['is_group_rate'] = (isset($data['is_group_rate']) && $data['is_group_rate'] == 1) ? 1 : 0;
                            $this->addCustomAgeRateGroups($rateGroupData, $standardRateDate);
                        }
                    }

                    $this->prepareRateSlotMapping($data, $standardRateDate);
                }
            }

            DB::commit();
            app(OctoRepository::class)->dispatchProductNotifications($data['tour_package_id']);
            return true;
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Summary of prepareRateSlotMapping
     * @param mixed $data
     * @param mixed $standardRateDate
     * @return void
     */
    public function prepareRateSlotMapping($data, $standardRateDate)
    {
        try {
            $rateSlotMappings = $this->rateSlotMapping::where('tour_package_id', $data['tour_package_id'])
                ->when(isset($data['affiliate_id']) && $data['affiliate_id'] != 0, function ($query) use ($data) {
                    return $query->where('affiliate_id', $data['affiliate_id']);
                }, function ($query) {
                    return $query->whereNull('affiliate_id');
                })
                ->where('custom_date_id', $standardRateDate->id)
                ->get();

            if ($rateSlotMappings->count()) {
                // Delete the existing rate groups if apply_on_slots is 0
                if ($standardRateDate->apply_on_slots == 0) {
                    foreach ($rateSlotMappings as $rateSlotMapping) {
                        $this->unassignSlotToDateRate(['id' => $rateSlotMapping->id]);
                    }
                } else {
                    $existingSlots = $rateSlotMappings->pluck('time_from')->toArray();
                    $newSlots = $data['slot_times'];

                    // Find slots to add (present in newSlots but not in existingSlots)
                    $slotsToAdd = array_diff($newSlots, $existingSlots);

                    // Find slots to delete (present in existingSlots but not in newSlots)
                    $slotsToDelete = array_diff($existingSlots, $newSlots);

                    // Add new slots
                    foreach ($slotsToAdd as $slotToAdd) {
                        $this->assignSlotToDateRate($data, $slotToAdd, $standardRateDate);
                    }

                    // Delete old slots
                    foreach ($slotsToDelete as $slotToDelete) {
                        $rateSlotMapping = $rateSlotMappings->where('time_from', $slotToDelete)->first();
                        if ($rateSlotMapping) {
                            $this->unassignSlotToDateRate(['id' => $rateSlotMapping->id]);
                        }
                    }
                }
            } elseif ($standardRateDate->apply_on_slots && count($data['slot_times'])) {
                // Assign all slots if no existing mappings are found and apply_on_slots is true
                foreach ($data['slot_times'] as $slotTime) {
                    $this->assignSlotToDateRate($data, $slotTime, $standardRateDate);
                }
            }
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Summary of addCustomAgeRateGroups
     * @param mixed $data
     * @param mixed $standardRateDate
     * @throws \Exception
     * @throws \App\Exceptions\Core\GeneralCoreException
     * @return mixed
     */
    public function addCustomAgeRateGroups($rateGroupData, $standardRateDate)
    {
        try {
            $package = $this->tourPackage::find($rateGroupData['tour_package_id']);

            $schedulingYear = date('Y', strtotime($standardRateDate->date_range_start));
            $cashPriceEnabled = cashPriceEnabled();

            $query = $this->tourPackageRate::where('tour_package_id', $rateGroupData['tour_package_id'])
                ->where('scheduling_year', $schedulingYear)
                ->when(isset($rateGroupData['affiliate_id']) && $rateGroupData['affiliate_id'] != 0, function ($query) use ($rateGroupData) {
                    return $query->where('affiliate_id', $rateGroupData['affiliate_id']);
                }, function ($query) {
                    return $query->whereNull('affiliate_id');
                })
                ->where('custom_date_id', $standardRateDate->id)
                ->where('mapping_key', $rateGroupData['mapping_key']);

            if (isset($rateGroupData['id']) && !empty($rateGroupData['id'])) {
                $query->where('id', '!=', $rateGroupData['id']);
            }

            $mappingKeyRateGroupExist = $query->count();

            if ($rateGroupData['mapping_key'] != 0 && $mappingKeyRateGroupExist > 0) {
                throw new Exception("Mapping key has been already taken.");
            }

            // Check if the rate group exists (for updating) or create a new one
            $rateGroup = isset($rateGroupData['id']) && !empty($rateGroupData['id'])
                ? $this->tourPackageRate::find($rateGroupData['id'])
                : new $this->tourPackageRate;

            if ($rateGroup) {
                $rateGroup->scheduling_year = $schedulingYear;
                $rateGroup->tour_package_id = $rateGroupData['tour_package_id'];
                $rateGroup->rate_for = $rateGroupData['rate_for'];
                $rateGroup->age_from = $rateGroupData['age_from'];
                $rateGroup->age_to = $rateGroupData['age_to'];
                $rateGroup->rate = $rateGroupData['rate'];
                $rateGroup->permit_fee = $rateGroupData['permit_fee'];
                $rateGroup->additional_charge = $rateGroupData['additional_charge'];
                $rateGroup->min_pax_allowed = $rateGroupData['min_pax_allowed'];
                $rateGroup->max_pax_allowed = $rateGroupData['max_pax_allowed'];
                $rateGroup->description = $rateGroupData['description'];
                $rateGroup->orderby = $rateGroupData['orderby'];
                $rateGroup->mapping_key = $rateGroupData['mapping_key'];
                $rateGroup->is_infant_rate = $rateGroupData['is_infant_rate'];
                $rateGroup->approval = $rateGroupData['approval'];
                $rateGroup->status = $rateGroupData['status'];
                $rateGroup->is_group_rate = $rateGroupData['is_group_rate'];
                $rateGroup->is_cash_rate = $rateGroupData['is_cash_rate'] ?? 0;
                if($cashPriceEnabled == 1 && $rateGroup->is_cash_rate == 1) {
                    $rateGroup->cash_rate = $rateGroupData['cash_rate'];
                    $rateGroup->cash_tax  = $rateGroupData['cash_tax'];
                }
                if (isset($standardRateDate->id) && $standardRateDate->id) {
                    $rateGroup->custom_date_id = $standardRateDate->id;
                }

                if (isset($rateGroupData['affiliate_id']) && !empty($rateGroupData['affiliate_id'])) {
                    $rateGroup->affiliate_id = $rateGroupData['affiliate_id'];
                }

                if ($package->is_group_rate_enabled) {
                    $rateGroup->min_group_size = $rateGroupData['min_group_size'];
                    $rateGroup->max_group_size = $rateGroupData['max_group_size'];
                }

                if ($package->is_partner_package) {
                    if($cashPriceEnabled == 1 && $rateGroup->is_cash_rate == 1) {
                        $subTotal = $package->is_group_rate_enabled ? $rateGroup->cash_rate + $rateGroup->cash_tax : $rateGroup->cash_rate + $rateGroup->cash_tax + $rateGroup->permit_fee;
                    } else {
                        $subTotal = $package->is_group_rate_enabled ? $rateGroup->rate + $rateGroup->additional_charge : $rateGroup->rate + $rateGroup->additional_charge + $rateGroup->permit_fee;
                    }
                
                    if ($rateGroupData['partner_fee_type'] == 'Percentage') {
                        $partnerFees = roundout((($subTotal * $rateGroupData['partner_fee_value']) / 100), 2);
                        $percentage = $rateGroupData['partner_fee_value'];
                    } else {
                        $partnerFees = $rateGroupData['partner_fee_value'];
                        $percentage = ($subTotal > 0) ? roundout(($partnerFees / $subTotal) * 100, 2) : 0.00;
                    }
                    $rateGroup->partner_fee_type = $rateGroupData['partner_fee_type'];
                    $rateGroup->partner_fee_percentage = $percentage;
                    $rateGroup->partner_fee_amount = $partnerFees;
                    //$rateGroup->margin = $rateGroupData['affiliate_fee'];
                }

                if (isset($rateGroupData['id']) && !empty($rateGroupData['id'])) {
                    $rateGroup->updated_by = Auth::id();
                    $rateGroup->updated_by_user_type = userType();
                } else {
                    $rateGroup->primary_rate = $rateGroupData['rate'];
                    $rateGroup->primary_additional_charge = $rateGroupData['additional_charge'];
                    $rateGroup->created_by = Auth::id();
                    $rateGroup->created_by_user_type = userType();
                }

                $rateGroup->save();
            }
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Summary of assignUnassignSlotToDateRate
     * @param mixed $data
     * @param mixed $slotTime
     * @param mixed $standardRateDate
     * @return mixed
     */
    public function assignSlotToDateRate($data, $slotTime, $standardRateDate)
    {
        try {
            $rateSlotMapping = $this->rateSlotMapping::where('tour_package_id', $data['tour_package_id'])
                ->when(isset($data['affiliate_id']) && $data['affiliate_id'] != 0, function ($query) use ($data) {
                    return $query->where('affiliate_id', $data['affiliate_id']);
                }, function ($query) {
                    return $query->whereNull('affiliate_id');
                })
                ->where('custom_date_id', $standardRateDate->id)
                ->where('time_from', $slotTime)
                ->first();

            $rateSlotMapping = $rateSlotMapping ?: new $this->rateSlotMapping;

            $rateSlotMapping->tour_package_id = $data['tour_package_id'];
            $rateSlotMapping->affiliate_id = ($data['affiliate_id'] != 0) ? $data['affiliate_id'] : null;
            $rateSlotMapping->custom_date_id = $standardRateDate->id;
            $rateSlotMapping->time_from = $slotTime;
            $rateSlotMapping->save();
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Summary of unassignSlotToDateRate
     * @param mixed $data
     * @return mixed
     */
    public function unassignSlotToDateRate($data)
    {
        try {
            $rateSlotMapping = $this->rateSlotMapping::find($data['id']);
            $rateSlotMapping->delete();

            return [
                'status' => 'success',
                'message' => 'Slot has been unassigned.'
            ];
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Summary of deleteCustomDateRate
     * @param mixed $data
     * @throws \Exception
     * @throws \App\Exceptions\Core\GeneralCoreException
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function deleteCustomDateRate($data)
    {
        DB::beginTransaction();
        try {
            $standardRateDate = $this->standardRateDate::find($data['id']);

            $tourPackageRates = $this->tourPackageRate::where('tour_package_id', $standardRateDate->tour_package_id)
                ->when(isset($standardRateDate->affiliate_id) && $standardRateDate->affiliate_id, function ($query) use ($standardRateDate) {
                    return $query->where('affiliate_id', $standardRateDate->affiliate_id);
                }, function ($query) {
                    return $query->whereNull('affiliate_id');
                })
                ->where('custom_date_id', $standardRateDate->id)
                ->get();

            if ($tourPackageRates->count()) {
                $tourPackageRateIds = $tourPackageRates->pluck('id');
                $bookingTransactCount = $this->bookingTransact::whereIn('tour_package_rate_id', $tourPackageRateIds)->count();

                if ($bookingTransactCount) {
                    throw new Exception("Bookings have been made using the age group that is available on this typical date.");
                } else {
                    // Delete the existing rate groups
                    foreach ($tourPackageRates as $tourPackageRate) {
                        $this->deletePackageRates(['id' => $tourPackageRate->id]);
                    }
                }
            }

            $rateSlotMappings = $this->rateSlotMapping::where('tour_package_id', $standardRateDate->tour_package_id)
                ->when(isset($standardRateDate->affiliate_id) && $standardRateDate->affiliate_id, function ($query) use ($standardRateDate) {
                    return $query->where('affiliate_id', $standardRateDate->affiliate_id);
                }, function ($query) {
                    return $query->whereNull('affiliate_id');
                })
                ->where('custom_date_id', $standardRateDate->id)
                ->get();

            if ($rateSlotMappings->count()) {
                // Delete the existing rate slot mappings
                foreach ($rateSlotMappings as $rateSlotMapping) {
                    $this->unassignSlotToDateRate(['id' => $rateSlotMapping->id]);
                }
            }

            $standardRateDate->delete();
            DB::commit();

            return response()->json([
                'success' => true
            ]);
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Delete the package rate.
     *
     * @param array $data
     * @return mixed
     */
    public function deletePackageRates($data)
    {
        DB::beginTransaction();
        try {
            $bookingTransactCount = $this->bookingTransact::where('tour_package_rate_id', $data['id'])->count();

            if ($bookingTransactCount) {
                throw new Exception("Age group you selected has been used in the bookings.");
            }

            $rateGroup = $this->tourPackageRate::find($data['id']);
            $rateGroup->deleted_by = Auth::id();
            $rateGroup->deleted_by_user_type = userType();
            $rateGroup->save();
            DB::commit();

            if ($rateGroup->delete()) {
                DB::commit();
                return [
                    'status' => true
                ];
            }

            return [
                'status' => false
            ];
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Get the time slots.
     *
     * @param array $data
     * @return mixed
     */
    public function getTimeSlots($data)
    {
        try {
            // Split the date range into two separate dates
            if (isset($data['date'])) {
                [$date_range_start, $date_range_end] = explode(' - ', $data['date']);

                // Add these to the request payload or process as needed
                $data['date_range_start'] = date('Y-m-d', strtotime($date_range_start));
                $data['date_range_end'] = date('Y-m-d', strtotime($date_range_end));
            }

            $slots = $this->slotTime::where('tour_package_id', $data['tour_package_id'])
                ->whereBetween('date', [$data['date_range_start'], $data['date_range_end']])
                ->groupBy('time_from')  // Ensure unique 'time_from' values
                ->orderBy('time_from', 'ASC')
                ->get('time_from');

            return [
                'slots' => $slots
            ];
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Summary of saveGlobalRates
     * @param mixed $data
     * @return mixed
     */
    public function saveGlobalRates($data)
    {
        DB::beginTransaction();
        try {
            if (!isset($data['rate_groups']) && empty($data['rate_groups'])) {
                throw new Exception('Please add a rate group to the package before saving.');
            }

            $package = $this->tourPackage::find($data['tour_package_id']);

            foreach ($data['rate_groups'] as $rateGroupData) {
                $query = $this->tourPackageRate::where('tour_package_id', $rateGroupData['tour_package_id'])
                    ->where('scheduling_year', $rateGroupData['scheduling_year'])
                    ->when(isset($rateGroupData['affiliate_id']) && $rateGroupData['affiliate_id'] != 0, function ($query) use ($rateGroupData) {
                        return $query->where('affiliate_id', $rateGroupData['affiliate_id']);
                    }, function ($query) {
                        return $query->whereNull('affiliate_id');
                    })
                    ->whereNull('custom_date_id')
                    ->where('mapping_key', $rateGroupData['mapping_key']);

                if (isset($rateGroupData['id']) && !empty($rateGroupData['id'])) {
                    $query->where('id', '!=', $rateGroupData['id']);
                }

                $mappingKeyRateGroupExist = $query->count();

                if ($rateGroupData['mapping_key'] != 0 && $mappingKeyRateGroupExist > 0) {
                    throw new Exception("Mapping key has been already taken.");
                }

                // Check if the rate group exists (for updating) or create a new one
                $rateGroup = isset($rateGroupData['id']) && !empty($rateGroupData['id'])
                    ? $this->tourPackageRate::find($rateGroupData['id'])
                    : new $this->tourPackageRate;

                if ($rateGroup) {
                    $rateGroup->scheduling_year = $rateGroupData['scheduling_year'];
                    $rateGroup->tour_package_id = $rateGroupData['tour_package_id'];
                    $rateGroup->rate_for = $rateGroupData['rate_for'];
                    $rateGroup->age_from = $rateGroupData['age_from'];
                    $rateGroup->age_to = $rateGroupData['age_to'];
                    $rateGroup->rate = $rateGroupData['rate'];
                    $rateGroup->permit_fee = $rateGroupData['permit_fee'];
                    $rateGroup->additional_charge = $rateGroupData['additional_charge'];
                    $rateGroup->min_pax_allowed = $rateGroupData['min_pax_allowed'];
                    $rateGroup->max_pax_allowed = $rateGroupData['max_pax_allowed'];
                    $rateGroup->description = $rateGroupData['description'];
                    $rateGroup->orderby = $rateGroupData['orderby'];
                    $rateGroup->mapping_key = $rateGroupData['mapping_key'];
                    $rateGroup->is_infant_rate = $rateGroupData['is_infant_rate'];
                    $rateGroup->status = $rateGroupData['status'];

                    if (isset($rateGroupData['affiliate_id']) && $rateGroupData['affiliate_id'] != 0) {
                        $rateGroup->affiliate_id = $rateGroupData['affiliate_id'];
                    }

                    if ($package->is_group_rate_enabled) {
                        $rateGroup->min_group_size = $rateGroupData['min_group_size'];
                        $rateGroup->max_group_size = $rateGroupData['max_group_size'];
                    }

                    if ($package->is_partner_package) {
                        $subTotal = ($package->is_group_rate_enabled) ? $rateGroup->rate + $rateGroup->additional_charge : $rateGroup->rate + $rateGroup->additional_charge + $rateGroup->permit_fee;
                        if ($rateGroupData['partner_fee_type'] == 'Percentage') {
                            $partnerFees = roundout((($subTotal * $rateGroupData['partner_fee_value']) / 100), 2);
                            $percentage = $rateGroupData['partner_fee_value'];
                        } else {
                            $partnerFees = $rateGroupData['partner_fee_value'];
                            $percentage = ($subTotal > 0) ? roundout(($partnerFees / $subTotal) * 100, 2) : 0.00;
                        }
                        $rateGroup->partner_fee_type = $rateGroupData['partner_fee_type'];
                        $rateGroup->partner_fee_percentage = $percentage;
                        $rateGroup->partner_fee_amount = $partnerFees;
                    }

                    if (isset($rateGroupData['id']) && !empty($rateGroupData['id'])) {
                        $rateGroup->updated_by = Auth::id();
                        $rateGroup->updated_by_user_type = userType();
                    } else {
                        $rateGroup->created_by = Auth::id();
                        $rateGroup->created_by_user_type = userType();
                    }

                    $rateGroup->save();
                }
            }

            DB::commit();
            return true;
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Get the package rates.
     *
     * @param array $data
     * @return mixed
     */
    public function getTourRatesBySlot($data)
    {
        try {
            $package = $this->tourPackage::find($data['tour_package_id']);
            $formattedDate = date('Y-m-d', strtotime($data['date']));
            $isBlackoutDate = isBlackoutDateEnabled($data['date']);
            $affiliateId = $data['affiliate_id'] == 0 ? NULL : $data['affiliate_id'];
            $affiliateId = ($isBlackoutDate) ? NULL : $affiliateId;
            $timeSlotId = $data['time_slot_id'];

            /* START: Custom Date Rate Validation */
            $standardRateDate = SurchargeDate::where('affiliate_id', $affiliateId)
                ->where('tour_package_id', $package->id)
                ->where('date_range_start', '<=', $formattedDate)
                ->where('date_range_end', '>=', $formattedDate)
                ->orderBy('priority', 'DESC')
                ->first();

            if (isset($standardRateDate) && $standardRateDate->apply_on_slots) {
                $customRateDate = isCustomDateRateEnabled($formattedDate, $package->id, $affiliateId, $timeSlotId);
            } else {
                $customRateDate = isCustomDateRateEnabled($formattedDate, $package->id, $affiliateId);
            }
            /* END */

            $rates = DB::table('tour_package_rates AS tpr')
                ->join('tour_packages AS tp', 'tp.id', '=', 'tpr.tour_package_id')
                ->where('scheduling_year', $data['scheduling_year'])
                ->where('tour_package_id', $data['tour_package_id'])
                ->where('tpr.deleted_by', 0)
                ->when(isset($data['affiliate_id']) && $data['affiliate_id'] && !$isBlackoutDate, function ($query) use ($data) {
                    return $query->where('affiliate_id', $data['affiliate_id']);
                }, function ($query) {
                    return $query->whereNull('affiliate_id');
                })
                ->when(isset($customRateDate) && $customRateDate, function ($query) use ($customRateDate) {
                    return $query->where('custom_date_id', $customRateDate);
                }, function ($query) {
                    return $query->whereNull('custom_date_id');
                })
                ->when(isset($data['global_rate']) && $data['global_rate'], function ($query) use ($data) {
                    return $query->where('global_rate', $data['global_rate']);
                })
                ->orderBy('orderby')
                ->get([
                    'tpr.*',
                    'tp.service_commission_percentage',
                    'tp.stripe_commission_percentage'
                ]);

            $paxWiseGroupRateIds = array();
            $paxWiseGroupRates = array();
            $paxWiseGroupAdditionalCharges = array();
            $rateGroupsNeedsApproval = array();

            if (cartView() == 1) {
                $cartTourPackageDetails = CartService::cartTourPackageDetails($data['tour_package_id']);
                if (!empty($cartTourPackageDetails) && !empty($cartTourPackageDetails['rate_group'])) {
                    foreach ($cartTourPackageDetails['rate_group'] as $key => $rateGroup) {
                        if (isset($cartTourPackageDetails['tourists'][$key])) {
                            $cartRateGroupTourists[$rateGroup] = $cartTourPackageDetails['tourists'][$key];
                        }
                    }
                }
            }

            if (!empty($rates)) {
                $mangerRequest = hasSetting('MANAGER_REQUEST');
                foreach ($rates as $rate) {
                    if ($data['affiliate_id'] != 0) {
                        $customerData = $this->affiliate::where('id', $data['affiliate_id'])->first();
                        $rate->affiliate_processing_percentage = ($isBlackoutDate) ? $rate->service_commission_percentage : (float) $customerData->affiliate_processing_percentage;
                        $rate->stripe_affiliate_percentage = ($isBlackoutDate) ? $rate->stripe_commission_percentage : $customerData->stripe_affiliate_percentage;

                        if (getNextYearCommissionPercentage($formattedDate)) {
                            if ($isBlackoutDate) {
                                $serviceCommission = getNormalCommissionPercentage($rate->tour_package_id);
                                $rateGroup->affiliate_processing_percentage = $serviceCommission['service_commission_percentage'];
                                $rateGroup->stripe_affiliate_percentage = $serviceCommission['stripe_commission_percentage'];
                            } else {
                                $affiliateCommission = getAffiliateCommissionPercentage($customerData->id);
                                $rate->affiliate_processing_percentage = (float) $affiliateCommission['affiliate_processing_percentage'];
                                $rate->stripe_affiliate_percentage = $affiliateCommission['stripe_affiliate_percentage'];
                            }
                        }
                    } elseif (getNextYearCommissionPercentage($formattedDate)) {
                        $serviceCommission = getNormalCommissionPercentage($rate->tour_package_id);
                        $rate->service_commission_percentage = $serviceCommission['service_commission_percentage'];
                        $rate->stripe_commission_percentage = $serviceCommission['stripe_commission_percentage'];
                    }

                    if ($rate->is_group_rate == 1) {
                        if ($rate->min_group_size > 0 && $rate->max_group_size > 0 && $rate->min_group_size <= $rate->max_group_size) {
                            for ($i = $rate->min_group_size; $i <= $rate->max_group_size; $i++) {
                                $paxWiseGroupRateIds[$i] = $rate->id;
                                $paxWiseGroupRates[$i] = (float)$rate->rate;
                                $paxWiseGroupAdditionalCharges[$i] = ($rate->additional_charge > 0) ? (float)$rate->additional_charge : 0.00;
                            }
                        }
                    }

                    if ($mangerRequest && userType() == 'Tour Operator' && $rate->approval == 1) {
                        $rateGroupsNeedsApproval[] = $rate->id;
                    }
                    if (!empty($cartRateGroupTourists) && isset($cartRateGroupTourists[$rate->id])) {
                        $rate->cart_tourists = $cartRateGroupTourists[$rate->id];
                    } else {
                        $rate->cart_tourists = 0;
                    }
                }
            }

            return [
                'is_group_rate_enabled' => $package->is_group_rate_enabled,
                'rates' => $rates,
                'rateGroupsNeedsApproval' => $rateGroupsNeedsApproval,
                'paxWiseGroupRateIds' => $paxWiseGroupRateIds,
                'paxWiseGroupRates' => $paxWiseGroupRates,
                'paxWiseGroupAdditionalCharges' => $paxWiseGroupAdditionalCharges
            ];
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Summary of saveScheduleEvent
     * @param mixed $data
     * @return mixed
     */
    public function saveScheduleEvent($data)
    {
        DB::beginTransaction();
        try {
            $dateTime = date('Y-m-d H:i:s', strtotime($data['date_time']));

            $globalRateScheduleExist = $this->globalRateSchedule::where('tour_package_id', $data['tour_package_id'])
                ->when(
                    isset($data['affiliate_id']) && $data['affiliate_id'] !== null,
                    function ($query) use ($data) {
                        return match ($data['affiliate_id']) {
                            0 => $query->whereNotNull('affiliate_id'), // Case for "all affiliates"
                            default => $query->where('affiliate_id', $data['affiliate_id']), // Specific affiliate
                        };
                    },
                    function ($query) {
                        return $query->whereNull('affiliate_id'); // Case when no affiliate is provided
                    }
                )
                ->where('date_time', $dateTime)
                ->where('scheduling_year', $data['scheduling_year'])
                ->first();

            if ($globalRateScheduleExist) {
                throw new Exception("The event has been already scheduled.");
            }

            $globalRateSchedule = new $this->globalRateSchedule;
            $globalRateSchedule->tour_package_id = $data['tour_package_id'];
            $globalRateSchedule->affiliate_id = $data['affiliate_id'] == 0 ? null : ($data['affiliate_id'] == 'all' ? 0 : $data['affiliate_id']);
            $globalRateSchedule->charge_amount = $data['charge_amount'];
            $globalRateSchedule->charge_type = $data['charge_type'];
            $globalRateSchedule->charge_mode = $data['charge_mode'];
            $globalRateSchedule->date_time = $dateTime;
            $globalRateSchedule->scheduling_year = $data['scheduling_year'];
            $globalRateSchedule->status = 'Active';
            $globalRateSchedule->save();
            DB::commit();
            return true;
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Summary of fetchScheduleEvents
     * @param mixed $data
     * @return mixed
     */
    public function fetchScheduleEvents($data)
    {
        try {
            $query = $this->globalRateSchedule::with(['tourPackage', 'affiliate'])
                ->where('tour_package_id', $data['tour_package_id'])
                ->when(
                    isset($data['affiliate_id']) && $data['affiliate_id'] != null,
                    function ($query) use ($data) {
                        return match ($data['affiliate_id']) {
                            0 => $query->whereNotNull('affiliate_id'), // Case for "all affiliates"
                            default => $query->where('affiliate_id', $data['affiliate_id']), // Specific affiliate
                        };
                    },
                    function ($query) {
                        return $query->whereNull('affiliate_id'); // Case when no affiliate is provided
                    }
                )
                ->where('scheduling_year', $data['scheduling_year']);

            // Clone the base query for present and past events
            $presentEvents = (clone $query)
                ->where('status', 'Active')
                ->orderBy('date_time')
                ->get();

            foreach ($presentEvents as $presentEvent) {
                $presentEvent->created_by_with_name = $this->getCreatedByUserName($presentEvent);
            }

            $pastEvents = (clone $query)
                ->where('status', 'Executed')
                ->orderBy('date_time')
                ->get();

            foreach ($pastEvents as $pastEvent) {
                $pastEvent->created_by_with_name = $this->getCreatedByUserName($pastEvent);
            }

            return [
                'present_events' => $presentEvents,
                'past_events' => $pastEvents
            ];
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Summary of deleteScheduleEvent
     * @param mixed $data
     * @return mixed
     */
    public function deleteScheduleEvent($data)
    {
        try {
            // Check if the event exists in the database.
            $globalRateScheduleEvents = $this->globalRateSchedule::find($data['id']);
            $globalRateScheduleEvents->deleted_by = Auth::id();
            $globalRateScheduleEvents->deleted_by_user_type = userType();

            // Update the deleted_at field to the current timestamp.
            $globalRateScheduleEvents->save();

            // Delete the corresponding global rate schedule record from the database.
            $globalRateScheduleEvents->delete();
            return true;
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Summary of getCreatedByUserName
     * @param mixed $model
     * @return string
     */
    public function getCreatedByUserName($model): string
    {
        if (!$model->created_by) {
            return "-";
        }

        // Admin and Staff both soft delete, so find() returns null once the
        // creator's account is removed. Look through the soft deletes to keep
        // the attribution, and fall back to "-" when the row is really gone.
        // Reading ->name off a null here used to raise an ErrorException that
        // the calling pages swallowed into a redirect.
        $user = match ($model->created_by_user_type) {
            "Admin" => Admin::withTrashed()->find($model->created_by),
            "Tour Operator" => Staff::withTrashed()->find($model->created_by),
            default => null,
        };

        return $user->name ?? "-";
    }
}
