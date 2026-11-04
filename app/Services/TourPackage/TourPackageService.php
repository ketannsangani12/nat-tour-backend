<?php

namespace App\Services\TourPackage;

use App\Constants\Constants;
use App\Models\Customer;
use App\Models\TourPackage;
use App\Models\TourPackageRate;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TourPackageService
{
    /**
     * Get tour packages
     * 
     * @return object
     */
    public static function getTourPackages(): object
    {
        // Get tour packages based on user type and staff assignment
        $packages = TourPackage::when(userType() == 'Affiliate', function ($q) {
            return $q->whereHas('packageAffiliate', function ($q) {
                $q->where('affiliate_id', Auth::id());
            });
        })
            ->when(departmentPackage(), function ($q) {
                return $q->whereHas('departments', function ($q) {
                    $q->where('department_id', Auth::user()->department_id);
                });
            })
            ->where('status', 'Active')
            ->orderBy('Order_by')
            ->orderBy('id')
            ->get(['id', 'name', 'stripe_destination_id', 'overbook_seats', 'has_contacts', 'is_combo_package', 'bundle_package', 'Order_by']);

        return $packages;
    }

    /**
     * Retrieves the commission rates based on whether an affiliate customer exists.
     *
     * @param int $affiliateCustomerId The ID of the affiliate customer (if any).
     * @param object $info The tour package information containing default commission rates.
     * @return array An array containing national and Stripe fees percentages.
     * @throws Exception If the affiliate customer is not found.
     */
    public static function getCommissionRates($affiliateCustomerId, $info)
    {
        // Initialize commission percentages with default values
        $natFeesPercentage = 0;
        $stripeFeesPercentage = 0;
        $isBlackoutDate = isBlackoutDateEnabled($info->date);
        // Check if there is no affiliate customer (i.e., a direct booking)
        if ($affiliateCustomerId == 0 || $isBlackoutDate) {
            $natFeesPercentage = $info->service_commission_percentage;
            $stripeFeesPercentage = $info->stripe_commission_percentage;
            if (getNextYearCommissionPercentage($info->date)) {
                $serviceCommission = getNormalCommissionPercentage($info->tour_package_id);
                $natFeesPercentage = $serviceCommission['service_commission_percentage'];
                $stripeFeesPercentage = $serviceCommission['stripe_commission_percentage'];
            }
        } else {
            // Retrieve the affiliate customer record
            $customer = Customer::find($affiliateCustomerId);

            // Ensure the customer exists before accessing properties
            if (!$customer) {
                throw new Exception("Affiliate customer not found.");
            }

            // Assign the customer's specific affiliate commission percentages
            $natFeesPercentage = $customer->affiliate_processing_percentage;
            $stripeFeesPercentage = $customer->stripe_affiliate_percentage;

            // Check if next year's affiliate commission rates should be applied
            if (self::shouldApplyNextYearAffiliateRates($info->date)) {
                $affiliateCommission = self::getAffiliateCommissionPercentage($customer->id);

                // Update commission percentages based on the new affiliate rates
                $natFeesPercentage = $affiliateCommission['affiliate_processing_percentage'];
                $stripeFeesPercentage = $affiliateCommission['stripe_affiliate_percentage'];
            }
        }

        // Return the calculated commission rates as an array
        return [
            'nat_fees_percentage' => $natFeesPercentage,
            'stripe_fees_percentage' => $stripeFeesPercentage,
        ];
    }

    /**
     * Calculate national and Stripe fees percentages based on customer type and booking source.
     *
     * @param object $request
     * @param object $customer
     * @param object $info
     * @return array
     */
    public static function getCommissionFeesPercentage($request, $customer, $info): array
    {
        $isBlackoutDate = isBlackoutDateEnabled($info->date);
        // Regular (non-affiliate) customer commissions
        $natFeesPercentage = $info->service_commission_percentage;
        $stripeFeesPercentage = $info->stripe_commission_percentage;
        // Check if the customer is an affiliate
        if ($customer->is_affiliate === 'Yes') {
            // Determine whether the booking came from the front end
            if (bookedFromFrontEnd($request->bookingID)) {
                $natFeesPercentage = $info->service_commission_percentage;
                $stripeFeesPercentage = $info->stripe_commission_percentage;
            } else {
                // Use affiliate-specific commission percentages
                $natFeesPercentage = ($isBlackoutDate) ? $info->service_commission_percentage : $customer->affiliate_processing_percentage;
                $stripeFeesPercentage = ($isBlackoutDate) ? $info->stripe_commission_percentage : $customer->stripe_affiliate_percentage;

                // Apply updated affiliate commission rates for the next year
                if (getNextYearCommissionPercentage($info->date)) {
                    $affiliateCommission = ($isBlackoutDate) ? getNormalCommissionPercentage($info->tour_package_id) : getAffiliateCommissionPercentage($customer->id);
                    $natFeesPercentage = ($isBlackoutDate) ? $affiliateCommission['service_commission_percentage'] : $affiliateCommission['affiliate_processing_percentage'];
                    $stripeFeesPercentage = ($isBlackoutDate) ? $affiliateCommission['stripe_commission_percentage'] : $affiliateCommission['stripe_affiliate_percentage'];
                }
            }
        } elseif (getNextYearCommissionPercentage($info->date)) {
            $serviceCommission = getNormalCommissionPercentage($info->tour_package_id);
            $natFeesPercentage = $serviceCommission['service_commission_percentage'];
            $stripeFeesPercentage = $serviceCommission['stripe_commission_percentage'];
        }

        return [
            'nat_fees_percentage' => $natFeesPercentage,
            'stripe_fees_percentage' => $stripeFeesPercentage,
        ];
    }
    /**
     * Determines whether next year's affiliate commission percentage should be applied.
     *
     * @param string $date The date of the booking/tour package.
     * @return bool True if next year's rates should be applied, false otherwise.
     */
    private static function shouldApplyNextYearAffiliateRates($date)
    {
        return getNextYearCommissionPercentage($date);
    }

    /**
     * Retrieves the updated affiliate commission percentages.
     *
     * @param int $customerId The ID of the affiliate customer.
     * @return array An array containing updated commission percentages.
     */
    private static function getAffiliateCommissionPercentage($customerId)
    {
        return getAffiliateCommissionPercentage($customerId);
    }

    /**
     * Get active bundle packages with their child packages.
     */
    public static function getBundlePackages(): Collection
    {
        return TourPackage::where('bundle_package', 1)
            ->where('status', 'Active')
            ->with('bundleChildren')
            ->orderBy('Order_by')
            ->get(['id', 'name', 'has_contacts', 'overbook_seats', 'is_combo_package', 'bundle_package']);
    }

    /**
     * Attach season_label to single, combo, and bundle package collections in one query.
     * Labels are derived from upcoming bookable slot dates grouped by package.
     */
    public static function attachSeasonLabels(
        Collection $singlePackages,
        Collection $comboPackages,
        Collection $bundlePackages
    ): void {
        $allPkgIds = array_unique(array_merge(
            $singlePackages->pluck('id')->toArray(),
            $comboPackages->pluck('id')->toArray(),
            $bundlePackages->pluck('id')->toArray()
        ));

        if (empty($allPkgIds)) {
            return;
        }

        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        $seasonRows = DB::table('tour_slot_times')
            ->select('tour_package_id', DB::raw('MONTH(date) as month'))
            ->whereIn('tour_package_id', $allPkgIds)
            ->where('date', '>=', now()->toDateString())
            ->where('bookable_status', '!=', 'Closed')
            ->where('hide_slot', 0)
            ->groupBy('tour_package_id', DB::raw('MONTH(date)'))
            ->orderBy('tour_package_id')
            ->orderBy(DB::raw('MONTH(date)'))
            ->get();

        $seasonMap = [];
        foreach ($seasonRows->groupBy('tour_package_id') as $pkgId => $rows) {
            $months = $rows->pluck('month')->map(fn($m) => (int) $m)->sort()->values()->unique()->toArray();
            $currentMonth = (int) now()->month;

            if (count(array_diff(range($currentMonth, 12), $months)) === 0) {
                $seasonMap[$pkgId] = 'Year-round';
                continue;
            }

            $ranges = [];
            $start  = $months[0];
            $prev   = $months[0];
            for ($i = 1; $i < count($months); $i++) {
                if ($months[$i] === $prev + 1) {
                    $prev = $months[$i];
                } else {
                    $ranges[] = [$start, $prev];
                    $start = $months[$i];
                    $prev  = $months[$i];
                }
            }
            $ranges[] = [$start, $prev];

            $seasonMap[$pkgId] = implode(', ', array_map(
                fn($r) => $r[0] === $r[1]
                    ? $monthNames[$r[0] - 1]
                    : $monthNames[$r[0] - 1] . ' - ' . $monthNames[$r[1] - 1],
                $ranges
            ));
        }

        $attach = fn($pkg) => tap($pkg, fn($p) => $p->season_label = $seasonMap[$p->id] ?? null);
        $singlePackages->transform($attach);
        $comboPackages->transform($attach);
        $bundlePackages->transform($attach);
    }

    public static function validateMinPaxAndGetRateType(array $tourists, array $rateGroups): int
    {
        // Check if min/max pax validation applies
        $minMaxPaxApply = in_array(self::getMinMaxPaxApplyStatus(), [Constants::STATUS_ONE, Constants::STATUS_TWO]);

        $isGroupRate = 0;

        foreach ($tourists as $index => $touristCount) {
            // Retrieve the rate group details
            $rateGroup = TourPackageRate::find($rateGroups[$index]);

            if (!$rateGroup) {
                throw new Exception("Invalid rate group selected.");
            }

            // Assign the group rate flag
            $isGroupRate = $rateGroup->is_group_rate;

            // Validate the minimum pax requirement
            if ($minMaxPaxApply && $rateGroup->min_pax_allowed > $touristCount) {
                throw new Exception("Please select a minimum of {$rateGroup->min_pax_allowed} {$rateGroup->rate_for} people to process your booking.");
            }
        }

        return $isGroupRate;
    }

    /**
     * Retrieves the min/max pax application status.
     *
     * @return int Returns the status of whether min/max pax applies.
     */
    private static function getMinMaxPaxApplyStatus(): int
    {
        return minMaxPaxApply();
    }

    /**
     * Fetches tour package details based on the slot time.
     *
     * @param int $slotTimeId The ID of the slot time.
     * @throws Exception If no tour package is found.
     * @return object The tour package details.
     */
    public static function getTourPackageSlotInfo(int $slotTimeId): object
    {
        $query = "
            SELECT
                tst.time_from, tst.time_to, tst.date, tst.time_slot_seats AS seats, 
                tp.tour_operator_id, tst.tour_package_id, tp.name AS package_name, 
                t_o.name AS tour_operator, tp.service_commission_percentage, 
                tp.stripe_commission_percentage, t_o.tenant_id, t_o.id AS tour_operator_id, 
                tp.bundle_package, tp.is_combo_package, tp.package_has_slots, tp.package_has_waiver,t_o.statement_descriptor,
                tp.package_has_permit, tp.has_contacts, t_o.stripe_destination_id,tp.is_partner_package,tp.stripe_destination_id as partnerDestinationId
            FROM
                tour_slot_times AS tst
            INNER JOIN tour_packages AS tp ON tp.id = tst.tour_package_id
            INNER JOIN tour_operators AS t_o ON t_o.id = tp.tour_operator_id
            WHERE
                tst.id = :slotTimeId
        ";

        $result = collect(DB::select($query, ['slotTimeId' => $slotTimeId]))->first();

        if (!$result) {
            throw new Exception('Unable to book the tour package.');
        }

        return $result;
    }
    /**
     * Fetches tour package details based on the slot time for create booking.
     *
     * @param int $slotTimeId The ID of the slot time.
     * @throws Exception If no tour package is found.
     * @return object The tour package details.
     */
    public static function getTourSlotInfo($slotTimeId, $userType)
    {
        $query = "SELECT
                tst.time_from, tst.time_to, tst.date, tst.time_slot_seats AS seats,
                tp.tour_operator_id, tst.tour_package_id, tp.name AS package_name,
                tp.important_notes, tp.things_to_bring, ct.state_id, st.permit_fee,
                t_o.name AS tour_operator, t_o.email AS tour_op_email,
                t_o.phone_number AS tour_op_phone, t_o.website AS tour_op_website,
                t_o.logo AS tour_op_logo, t_o.cancellation_policy,
                tp.stripe_destination_id as packageDestinationId,tp.service_commission_percentage, tp.stripe_commission_percentage,tp.arrival_time_duration,
                tp.is_group_rate_enabled,tp.is_partner_package,t_o.tenant_id, t_o.stripe_destination_id, t_o.id AS tour_operator_id,
                t_o.statement_descriptor, t_o.card_reader_prefix,t_o.stripe_destination_id as tourOperatorDestinationId,
                tp.email AS packageEmail, tp.from_name AS packageFromName,
                tp.from_email AS packageFromEmail, tp.api_key AS packageApiKey,
                tp.bundle_package, tp.package_has_slots, tp.package_has_waiver,tp.attach_waiver_pdf,tp.waiver_pdf,
                tp.package_has_permit, tp.has_contacts,tp.is_partner_package,
                (
                    SELECT SUM(tbm.tourists)
                    FROM tour_bookings_master AS tbm
                    WHERE tbm.tour_slot_id = tst.id AND tbm.status != 'Cancelled'
                    GROUP BY tbm.tour_slot_id
                ) AS booked_seats
            FROM tour_slot_times AS tst
            INNER JOIN tour_packages AS tp ON tp.id = tst.tour_package_id
            INNER JOIN tour_operators AS t_o ON t_o.id = tp.tour_operator_id
            INNER JOIN cities AS ct ON ct.id = t_o.city_id
            INNER JOIN states AS st ON st.id = ct.state_id
            WHERE tst.id = ?";

        $bindings = [$slotTimeId];

        if ($userType == 'Tour Operator' || $userType == 'Affiliate') {
            $query .= ' AND tp.tour_operator_id = ?';
            $bindings[] = Auth::user()->tour_operator_id;
        }

        return collect(DB::select($query, $bindings))->first();
    }
}
