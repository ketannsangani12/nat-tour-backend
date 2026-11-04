<?php

namespace App\Services;

use App\Models\TourBookingHistory;
use App\Models\BookingTransact;
use App\Jobs\DetectPayoutAdjustment;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\TimeSlot\TouristService;
use App\Services\TimeSlot\TimeSlotService;
use App\Services\Octo\OctoService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class BokunService
{
    /**
     * Get summary of package details
     * @param string $productCode

     * @return object
     */
    public static function getPackageDetails($productCode)
    {
        $query = "
            SELECT
                t_o.id AS operator_id,
                tp.id AS package_id,
                tp.bokun_product_code,
                c.id AS affiliate_id,
                `tp`.`service_commission_percentage`,
                `tp`.`stripe_commission_percentage`,
                `tp`.`ota_duration`,
                `tp`.`package_has_slots`
            FROM tour_packages tp
            INNER JOIN tour_operators t_o ON t_o.id = tp.tour_operator_id
            INNER JOIN customers c ON c.tour_operator_id = t_o.id
            WHERE tp.bokun_product_code = ?
            AND c.bokun = 1
            AND c.is_affiliate = 'Yes'
            AND c.deleted_by = 0
            AND tp.status = 'Active'
            AND tp.deleted_by = 0
        ";

        $package = collect(DB::select($query, [$productCode]))->first();

        return $package;
    }


    public static function rateGroups($package, $api = null, $year = null)
    {
        $rateYear = $year ?? Carbon::now()->year;

        $query = DB::table('tour_package_rates AS tpr')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tpr.tour_package_id')
            ->join('customers AS c', 'c.id', '=', 'tpr.affiliate_id')
            ->where([
                ['tp.id', '=', $package->package_id],
                ['tp.bokun_product_code', '=', $package->bokun_product_code],
                ['tpr.deleted_by', '=', 0],
                ['tpr.scheduling_year', '=', $rateYear],
                ['tpr.status', '=', 'Active'],
                ['tpr.affiliate_id', '=', $package->affiliate_id],
                // ['tpr.global_rate', '=', 1],
                ['c.bokun', '=', 1]
            ])
            ->whereNull('tpr.custom_date_id')
            ->orderBy('tpr.orderby', 'ASC');

        // Select fields based on API type
        $fields = ($api === 'availability') ? [
            'tpr.id',
            'tpr.age_from',
            'tpr.age_to',
            'tpr.rate_for',
            'tpr.rate',
            'tpr.additional_tax_percentage',
            'tpr.processing_charge_percentage',
            'tpr.additional_charge',
            'tpr.permit_fee',
            'tpr.global_rate',
            'tpr.custom_date_id',
            'tpr.is_group_rate',
            'tpr.affiliate_id',
            'tpr.is_infant_rate'
        ] : [
            'tpr.id',
            'tpr.age_from',
            'tpr.age_to',
            'tpr.rate_for'
        ];

        // Return the result, mapping values only when necessary
        return $query->select($fields)->get()->map(fn($rate) => ($api === 'availability')
            ? $rate
            : [
                'id' => (string) $rate->id,
                'label' => $rate->rate_for,
                'minAge' => $rate->age_from,
                'maxAge' => $rate->age_to,
            ]);
    }


    public static function rates($package)
    {
        $rates = collect(self::rateGroups($package, 'availability'));

        $rateTypes = $rates
            ->map(fn($rate) => match (true) {
                $rate->custom_date_id === null => ['id' => '1', 'label' => 'Global Rate']
                // $rate->custom_date_id === null && $rate->global_rate == 1 => ['id' => '1', 'label' => 'Global Rate']
                // $rate->custom_date_id !== null => ['id' => '2', 'label' => 'Custom Rate'],
                // $rate->is_group_rate == 1 => ['id' => '3', 'label' => 'Group Rate'],
                // default => null
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        return $rateTypes;
    }


    public static function generateReservationCode($length = 8)
    {
        do {
            $code = Str::upper(Str::random($length)); // Example: 8-char like '4G7ZK9LQ'
        } while (
            DB::table('bokun_reservations')->where('reservation_code', $code)->exists()
        );

        return $code;
    }

    /**
     * Summary of getTimeSlotsByDate
     * @param mixed $tourOperatorId
     * @param mixed $tourPackageId
     * @param mixed $affiliateId
     * @param mixed $startDate
     * @param mixed $dateRange
     * @param mixed $endDate
     * @return array
     */
    public static function getTimeSlotsByDate($tourOperatorId, $tourPackageId, $affiliateId, $startDate, $endDate)
    {
        $slotTimesQuery = self::initTimeSlotsQuery($tourPackageId, $affiliateId);

        // Append WHERE clauses securely using parameter binding
        $slotTimesQuery .= "
            WHERE tst.date BETWEEN ? AND ?
            AND tst.tour_package_id = ?
            AND tp.tour_operator_id = ?
            AND tst.hide_slot = 0
            AND tsd.deleted_by = 0
            AND tst.deleted_by = 0
            AND tst.bookable_status != 'Closed'
            ORDER BY tst.time_from ASC
        ";

        // Run the query using bindings to avoid SQL injection
        $slotTimes = DB::select($slotTimesQuery, [$startDate, $endDate, $tourPackageId, $tourOperatorId]);

        $affiliate = (object) ['id' => $affiliateId];
        $request = (object) ['tourPackageId' => $tourPackageId];

        $affiliateTimeSlots = TimeSlotService::getAffiliateTimeSlots($affiliate, $request);

        $activeReservations = DB::table('bokun_reservations')
            ->where('tour_package_id', $tourPackageId)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('expired_at', '>', now())
            ->select('tour_slot_id', DB::raw('SUM(seats) as total_seats'))
            ->groupBy('tour_slot_id')
            ->get()
            ->keyBy('tour_slot_id');

        $timeSlots = collect($slotTimes)->map(function ($slot) use ($startDate, $endDate, $tourPackageId, $affiliateId, $affiliateTimeSlots, $activeReservations) {
            $activeReservationSeats = $activeReservations->has($slot->id) ? (int) $activeReservations->get($slot->id)->total_seats : 0;
            return self::limitedSeatsCalculation($startDate, $endDate, $slot, $tourPackageId, $affiliateId, $affiliateTimeSlots, $activeReservationSeats);
        })->toArray();

        return $timeSlots;
    }

    public static function initTimeSlotsQuery($tourPackageId, $affiliateId)
    {
        return "
            SELECT 
                tp.id AS tour_package_id,
                tst.id,
                tst.time_from,
                tst.time_to,
                tst.time_slot_seats AS seats,
                tst.bookable_status,
                tst.date,
                t_op.timezone,
                IFNULL(tst.time_slot_seats, 0) AS seats,

                -- Total tourists for this affiliate
                IFNULL((
                    SELECT SUM(tbm.tourists)
                    FROM tour_bookings_master AS tbm
                    WHERE tbm.tour_slot_id = tst.id
                    AND tbm.status != 'Cancelled'
                    AND tbm.tour_package_id = {$tourPackageId}
                    AND tbm.customer_id = {$affiliateId}
                    GROUP BY tbm.tour_slot_id, tbm.tour_package_id
                ), 0) AS tourists,

                -- Total affiliate tourists
                IFNULL((
                    SELECT SUM(tbm.tourists)
                    FROM tour_bookings_master AS tbm
                    JOIN customers AS c ON c.id = tbm.customer_id
                    WHERE tbm.tour_slot_id = tst.id
                    AND tbm.status != 'Cancelled'
                    AND tbm.tour_package_id = {$tourPackageId}
                    AND c.is_affiliate = 'Yes'
                    AND tbm.customer_id = {$affiliateId}
                    GROUP BY tbm.tour_slot_id, tbm.tour_package_id
                ), 0) AS afl_tourists,

                -- Total booked seats
                IFNULL((
                    SELECT SUM(tbm2.tourists)
                    FROM tour_bookings_master AS tbm2
                    WHERE tbm2.tour_slot_id = tst.id
                    AND tbm2.status != 'Cancelled'
                    GROUP BY tbm2.tour_slot_id
                ), 0) AS booked_seats,

                -- Is Parent
                COALESCE((
                    SELECT DISTINCT tpf.is_parent 
                    FROM tour_package_families AS tpf 
                    WHERE tpf.tour_package_id = tsd.tour_package_id 
                    AND tst.time_from = tpf.time_from 
                    AND tpf.tour_package_id = {$tourPackageId}
                ), 0) AS is_parent,

                -- Group index
                COALESCE((
                    SELECT DISTINCT tpf.group_index 
                    FROM tour_package_families AS tpf 
                    WHERE tpf.tour_package_id = tsd.tour_package_id 
                    AND tst.time_from = tpf.time_from 
                    AND tpf.tour_package_id = {$tourPackageId}
                ), 0) AS group_index

            FROM tour_slot_times AS tst
            INNER JOIN tour_packages AS tp ON tp.id = tst.tour_package_id
            INNER JOIN tour_operators AS t_op ON t_op.id = tp.tour_operator_id
            INNER JOIN tour_slot_dates AS tsd ON tsd.id = tst.tour_slot_id
        ";
    }

    /**
     * Check the overbooking for the specific slot user trying to book
     * @param mixed $slotTime
     * @param mixed $tourPackageId
     * @param mixed $startDate
     * @param mixed $affiliateId
     * @return mixed
     */
    public static function checkSelectedTimeSlotBooking($slotTime, $tourPackageId, $startDate, $affiliateId, $excludeReservationCode = null)
    {
        $query = self::initTimeSlotsQuery($tourPackageId, $affiliateId) . "
            WHERE tst.date = ?
            AND tst.time_from = ?
            AND tst.bookable_status != 'Closed'
            AND tst.hide_slot = 0
            AND tsd.deleted_by = 0
            AND tst.deleted_by = 0
            AND tp.id = ?
        ";

        $timeSlot = collect(DB::select($query, [$startDate, $slotTime, $tourPackageId]))->first();

        if (!$timeSlot) {
            return null;
        }

        $affiliate = (object) ['id' => $affiliateId];
        $request = (object) ['tourPackageId' => $tourPackageId];

        $affiliateTimeSlots = TimeSlotService::getAffiliateTimeSlots($affiliate, $request);

        $reservationQuery = DB::table('bokun_reservations')
            ->where('tour_slot_id', $timeSlot->id)
            ->where('expired_at', '>', now());

        if ($excludeReservationCode) {
            $reservationQuery->where('reservation_code', '!=', $excludeReservationCode);
        }

        $activeReservationSeats = (int) $reservationQuery->sum('seats');

        return self::limitedSeatsCalculation($startDate, $startDate, $timeSlot, $tourPackageId, $affiliateId, $affiliateTimeSlots, $activeReservationSeats);
    }

    public static function packageRelations($tourPackageId, $year, $startDate, $endDate)
    {
        /* START - mapping tour package and reducing the relational seats */
        $packageRelationSetting = hasSetting('PACKAGE_RELATION');
        $tourPackageFamily = [];
        if ($packageRelationSetting) {
            $tourPackageFamily = DB::select("SELECT *
                FROM tour_package_families
                WHERE relation_year = $year
                AND parent_package_id IN (
                    SELECT parent_package_id
                    FROM tour_package_families
                    WHERE tour_package_id = $tourPackageId
                        AND relation_year = $year
                )");
        }
        /* END */

        /**
         * STEP 2: Load all relation packages in one go
         */
        $relatedPackages = [];
        $groupedRelations = collect();
        if ($packageRelationSetting) {
            $relatedPackages = Cache::remember(
                "tour_package_families_{$tourPackageId}_{$year}",
                3600,
                fn() => DB::table('tour_package_families')
                    ->where('relation_year', $year)
                    ->whereIn('parent_package_id', function ($query) use ($tourPackageId, $year) {
                        $query->select('parent_package_id')
                            ->from('tour_package_families')
                            ->where('tour_package_id', $tourPackageId)
                            ->where('relation_year', $year);
                    })
                    ->get()
            );

            $groupedRelations = $relatedPackages->groupBy('group_index');
        }

        /**
         * STEP 3: Preload all slots with tourist counts (for all related packages)
         */
        $allSlotData = collect();

        if ($packageRelationSetting && $relatedPackages->isNotEmpty()) {
            $packageIds = $relatedPackages->pluck('tour_package_id')->unique()->push($tourPackageId);

            // Get slot data with fallback to direct booking calculation
            $slotSummaryData = DB::table('tour_slot_times AS tst')
                ->leftJoin('tour_slot_summaries AS tss', function ($join) {
                    $join->on('tss.tour_slot_id', '=', 'tst.id')
                            ->whereColumn('tss.tour_package_id', '=', 'tst.tour_package_id');
                })
                ->select(
                    'tst.date',
                    'tst.time_from',
                    'tst.tour_package_id',
                    DB::raw('COALESCE(tst.time_slot_seats, 0) AS total_seats'),
                    DB::raw('IFNULL(tss.booked_tourists, 0) AS summary_tourists')
                )
                ->whereBetween('tst.date', [$startDate, $endDate])
                ->whereIn('tst.tour_package_id', $packageIds)
                ->get();

            // Get direct booking data as fallback
            $bookingData = DB::table('tour_slot_times AS tst')
                ->join('tour_bookings_master AS tbm', function ($join) {
                    $join->on('tbm.tour_slot_id', '=', 'tst.id')
                            ->whereColumn('tbm.tour_package_id', '=', 'tst.tour_package_id')
                            ->where('tbm.status', '!=', 'Cancelled');
                })
                ->select(
                    'tst.date',
                    'tst.time_from',
                    'tst.tour_package_id',
                    DB::raw('SUM(tbm.tourists) AS booking_tourists')
                )
                ->whereBetween('tst.date', [$startDate, $endDate])
                ->whereIn('tst.tour_package_id', $packageIds)
                ->groupBy('tst.date', 'tst.time_from', 'tst.tour_package_id')
                ->get();

            // Merge the data
            $allSlotData = collect();
            foreach ($slotSummaryData as $slot) {
                $key = "{$slot->tour_package_id}|{$slot->date}|{$slot->time_from}";
                
                // Find matching booking data
                $booking = $bookingData->first(function ($b) use ($slot) {
                    return $b->tour_package_id == $slot->tour_package_id 
                        && $b->date == $slot->date 
                        && $b->time_from == $slot->time_from;
                });
                
                // Always use booking data (which excludes cancelled bookings) instead of summary data
                $tourists = $booking->booking_tourists ?? 0;
                
                $allSlotData->put($key, [(object)[
                    'date' => $slot->date,
                    'time_from' => $slot->time_from,
                    'tour_package_id' => $slot->tour_package_id,
                    'total_seats' => $slot->total_seats,
                    'total_tourists' => $tourists
                ]]);
            }
        }

        return [
            'packageRelationSetting' => $packageRelationSetting,
            'groupedRelations' => $groupedRelations,
            'tourPackageFamily' => $tourPackageFamily,
            'allSlotData' => $allSlotData
        ];
    }

    /**
     * Returns seats consumed by active (non-expired) Bokun reservations for a slot.
     * Mirrors OctoService::getActiveReservationSeats() for the Bokun reservation path.
     * Infants are excluded — stored separately in bokun_reservations.infants and never consume capacity.
     */
    public static function getActiveReservationSeats(int $slotId): int
    {
        return (int) DB::table('bokun_reservations')
            ->where('tour_slot_id', $slotId)
            ->where('expired_at', '>', now())
            ->sum('seats');
    }

    /**
     * Summary of limitedSeatsCalculation
     * @param mixed $timeSlot
     * @param integer $tourPackageId
     * @param integer $affiliateId
     *
     * @return object
     */
    public static function limitedSeatsCalculation($startDate, $endDate, $timeSlot, $tourPackageId, $affiliateId, $affiliateTimeSlots, $activeReservationSeats = 0)
    {
        $year = Carbon::parse($startDate)->format('Y');
        $packageRelations = self::packageRelations($tourPackageId, $year, $startDate, $endDate);

        /* START - mapping tour package and reducing the relational seats */
        $relationPackage = [];
        $totalTourists = 0;
        if ($packageRelations['packageRelationSetting'] && $packageRelations['groupedRelations']->has($timeSlot->group_index)) {
            $relationPackage = array_filter($packageRelations['tourPackageFamily'], function ($obj) use ($timeSlot) {
                return $obj->tour_package_id == $timeSlot->tour_package_id && $obj->group_index == $timeSlot->group_index;
            });

            $relations = $packageRelations['groupedRelations'][$timeSlot->group_index];
            $totalTourists = TouristService::countTotalTouristsOptimized($relations, $packageRelations['allSlotData'], $timeSlot);
        }
        /* END */

        $affiliateTimeSlots = (isset($affiliateTimeSlots) && !empty($affiliateTimeSlots)) ? $affiliateTimeSlots : [];
        $desiredSlot = collect($affiliateTimeSlots)->first(function ($slot) use ($timeSlot) {
            return $slot->time_from === $timeSlot->time_from;
        });

        // Actual seats calculation
        $bookedSeatsPerSlot = ((int) $totalTourists > 0 && !empty($relationPackage)) ? (int) $totalTourists : (int) $timeSlot->booked_seats;
        $slotSeats = $timeSlot->seats;
        $totalSeatsPerSlot = $slotSeats;

        // Affiliate seats calculation
        $tourists = (int) $timeSlot->afl_tourists;

        // Affiliate seat limit calculation
        if ($desiredSlot && isset($desiredSlot->seat_limit)) {
            if ($slotSeats > 0) {
                // Use the minimum of slot seats and affiliate seat limit
                $seats = min($slotSeats, $desiredSlot->seat_limit);
            } else {
                // If slot seats is 0, use affiliate seat limit directly
                $seats = $desiredSlot->seat_limit;
            }
        } else {
            // If no affiliate-specific limit, use the global slot capacity
            $seats = $slotSeats;
        }

        // Include active OCTO ON_HOLD bookings so both reservation systems
        // draw from the same global pool of seats.
        $octoActiveSeats = OctoService::getActiveReservationSeats((int) $timeSlot->id);

        // Calculate affiliate availability
        // Global remaining capacity (total slot capacity - all bookings - active Bokun reservations - active OCTO holds)
        $globalRemaining = max(0, $totalSeatsPerSlot - $bookedSeatsPerSlot - $activeReservationSeats - $octoActiveSeats);

        // For child packages: also check parent package remaining capacity
        if ($packageRelations['packageRelationSetting'] && !empty($relationPackage) && $timeSlot->is_parent == 0) {
            // This is a child package - need to check parent capacity
            $totalBookingsAllPackages = 0;
            $parentSeats = 0;
            
            foreach ($relations as $relation) {
                $relationSlotKey = "{$relation->tour_package_id}|{$timeSlot->date}|{$timeSlot->time_from}";
                if ($packageRelations['allSlotData']->has($relationSlotKey)) {
                    $relationSlotData = $packageRelations['allSlotData']->get($relationSlotKey);
                    if (!empty($relationSlotData) && isset($relationSlotData[0])) {
                        // Sum all bookings (parent + all children)
                        $totalBookingsAllPackages += $relationSlotData[0]->total_tourists;
                        
                        // Get parent capacity
                        if ($relation->is_parent == 1) {
                            $parentSeats = $relationSlotData[0]->total_seats;
                        }
                    }
                }
            }
            
            if ($parentSeats > 0) {
                // Parent remaining = parent capacity - all bookings (parent + children)
                $parentRemaining = max(0, $parentSeats - $totalBookingsAllPackages);
                // Child availability is constrained by parent remaining capacity
                $globalRemaining = min($globalRemaining, $parentRemaining);
            }
        }
        
        // If affiliate has specific seat limits, calculate their remaining capacity
        if ($desiredSlot && isset($desiredSlot->seat_limit)) {
            $affiliateRemaining = max(0, $seats - $tourists - $activeReservationSeats - $octoActiveSeats);
            $availableSeats = min($globalRemaining, $affiliateRemaining);
        } else {
            // No affiliate seat limits configured - they cannot book
            $availableSeats = 0;
        }

        $overbook = ($desiredSlot && isset($desiredSlot->seat_limit) && $tourists > $desiredSlot->seat_limit) ? abs($tourists - $desiredSlot->seat_limit) : 0;

        $timeSlot->seats = $seats;
        $timeSlot->tourists = (string) $tourists;
        $timeSlot->global_booked_seats = $bookedSeatsPerSlot;
        $timeSlot->booked_seats = $tourists;
        $timeSlot->overbooked = $overbook;
        $timeSlot->available_seats = $availableSeats;

        if ($timeSlot->bookable_status === 'Only by Phone') {
            $timeSlot->bookable_status = 'Closed';
            $timeSlot->available_seats = 0; // Force zero to be safe
        }

        return $timeSlot;
    }

    /**
     * Execute the service.
     *
     * @return void
     */
    public static function storePricingInHistory($booking, $transactionId, $historyId, $bookingVia)
    {
        $tourBookingHistory = TourBookingHistory::with('tourBooking.customer')
            ->where('id', $historyId)
            ->first();

        $tourBookingTransact = BookingTransact::where('tour_booking_id', $booking->id)
            ->where('deleted_by', 0)
            ->get();

        $ticketCostTotal = 0;
        $permitFeesTotal = 0;

        foreach ($tourBookingTransact as $pax) {
            $ticketCost = $pax->tourists * ($pax->rate + $pax->additional_charge + $pax->permit_fee);
            $ticketCostTotal += $ticketCost;

            $permitFee = $pax->tourists * $pax->permit_fee;
            $permitFeesTotal += $permitFee;
        }

        $tourBookingHistory->ticket_cost = $ticketCostTotal;
        $tourBookingHistory->permit_fees = $permitFeesTotal;
        $tourBookingHistory->platform_fees = 0;
        $tourBookingHistory->transaction_id = $transactionId;
        $tourBookingHistory->payment_type = $bookingVia;
        $tourBookingHistory->payment_method = 'Full Payment';
        $tourBookingHistory->is_processed = 1;
        $tourBookingHistory->save();
    }

    /**
     * Execute the service to Cancel Booking From Bokun Via Viator|GetYourGuide|Others.
     *
     * @return mixed
     */
    public static function cancelBooking($tourBooking, $refundVia, $requestForm, $cancelNote)
    {
        $transaction = Transaction::where('tour_booking_id', $tourBooking->booking_id)
            ->where('status', 'Completed')
            ->first();

        $customer = Customer::find($tourBooking->customer_id);

        $netAmount = 0;
        $refundKeepUs = 0;

        $totalRefundedAmount = Transaction::where('tour_booking_id', $tourBooking->booking_id)
            ->where('status', 'Refund')
            ->sum('amount');

        $histPaymentDetails = TourBookingHistory::where('transaction_id', $transaction->id)
            ->where('operation', 'Payment')
            ->first(['ticket_cost', 'platform_fees']);

        $netAmount += max(0, $histPaymentDetails->ticket_cost - $totalRefundedAmount);
        $refundKeepUs += $histPaymentDetails->platform_fees;

        // Create a new refund transaction
        $transactionId = DB::table('transactions')
            ->insertGetId([
                'tour_booking_id' => $tourBooking->booking_id,
                'amount' => $netAmount,
                'transaction_number' => null,
                'via' => $refundVia,
                'payment_type' => 0,
                'response_init' => '',
                'response_end' => '',
                'response_end_datetime' => now(),
                'status' => 'Refund',
                'visitor' => request()->ip(),
                'request_from' => $requestForm,
                'created_by' => $customer->id,
                'created_by_user_type' => 'Affiliate',
                'created_at' => now()
            ]);

        // Insert cancellation history
        $historyId = DB::table('tour_booking_history')->insertGetId([
            'tour_booking_id' => $tourBooking->booking_id,
            'transaction_id' => $transactionId ?? null,
            'tourists' => $tourBooking->tourists + $tourBooking->infants,
            'operation' => 'Cancelled',
            'details' => json_encode([
                'amount' => $netAmount,
                'refund_keep_us' => $refundKeepUs,
                'payment_type' => $refundVia,
                'refund_type' => 'Full Refund',
                'note' => $cancelNote
            ], JSON_NUMERIC_CHECK),
            'operation_at' => mysqlDT(),
            'operation_by' => $tourBooking->customer_id,
            'operation_by_user_type' => 'Affiliate',
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        // Update history details
        if ($historyId) {
            TourBookingHistory::where('id', $historyId)->update([
                'ticket_cost' => $netAmount,
                'transaction_id' => $transactionId ?? null,
                'payment_type' => $refundVia,
                'payment_method' => 'Full Refund',
                'is_processed' => 1
            ]);
        }

        DB::commit();

        DetectPayoutAdjustment::dispatch($tourBooking->booking_id, $historyId, 'Cancelled');
    }

    /**
     * Store refund history for Bokun amendment (delete pax).
     */
    public static function storeRefundDataInHistory(
        int $bookingId,
        int $tourists,
        string $createdByWithName,
        string $refundType,
        float $amount,
        string $paymentVia,
        float $refundKeepUs,
        string $deleted,
        float $ticketCost,
        float $permitFees,
        int $affiliateId,
        float $feeAmount = 0,
        ?int $transactionId = null,
        float $paymentTotal = 0,
        float $refundTotal = 0
    ): int {
        $options = [
            'amount'               => $amount,
            'created_by_with_name' => $createdByWithName,
            'refund_type'          => $refundType,
            'payment_type'         => $paymentVia,
            'refund_keep_us'       => $refundKeepUs,
            'fees'                 => $feeAmount,
        ];
        if ($refundType !== 'Partial Refund') {
            $options['deleted'] = $deleted;
        }
        if ($refundType === 'Partial Refund' && $paymentTotal > 0) {
            $options['paymenttotal'] = $paymentTotal;
        }
        if ($refundType === 'Partial Refund' && $refundTotal > 0) {
            $options['refundtotal'] = $refundTotal;
        }

        $historyId = DB::table('tour_booking_history')->insertGetId([
            'tour_booking_id'        => $bookingId,
            'tourists'               => $tourists,
            'operation'              => 'Refund',
            'ticket_cost'            => $ticketCost,
            'permit_fees'            => $permitFees,
            'payment_type'           => $paymentVia,
            'payment_method'         => $refundType,
            'transaction_id'         => $transactionId,
            'is_processed'           => 1,
            'details'                => json_encode($options, JSON_NUMERIC_CHECK),
            'operation_at'           => mysqlDT(),
            'operation_by'           => $affiliateId,
            'operation_by_user_type' => 'Affiliate',
            'updated_at'             => Carbon::now(),
            'created_at'             => Carbon::now(),
        ]);

        DetectPayoutAdjustment::dispatch($bookingId, $historyId, 'Refund');

        return $historyId;
    }

    /**
     * Store cash payment history for Bokun amendment (add pax).
     */
    public static function storePaymentInHistory(
        int $bookingId,
        int $tourists,
        array $data,
        int $changeAmount,
        string $createdByWithName,
        float $ticketCost,
        float $permitFees,
        string $paymentVia,
        string $paymentMethod,
        int $affiliateId,
        ?int $transactionId = null
    ): int {
        $options = [
            'amount'               => $data['amount'],
            'payment_method'       => $data['payment_method'],
            'payment_type'         => !empty($data['payment_type']) ? $data['payment_type'] : 'Cash',
            'created_by_with_name' => $createdByWithName,
            'discount_promo_code'  => $data['discount_promo_code'] ?? '',
        ];

        if (!empty($data['fees'])) {
            $options['fees'] = $data['fees'];
        }
        if (!empty($data['paymenttotal'])) {
            $options['paymenttotal'] = $data['paymenttotal'];
        }
        if (!empty($data['refundtotal'])) {
            $options['refundtotal'] = $data['refundtotal'];
        }

        $historyId = DB::table('tour_booking_history')->insertGetId([
            'tour_booking_id'          => $bookingId,
            'tourists'                 => $tourists,
            'change_amount'            => $changeAmount,
            'package_swipe_history_id' => $data['swipeHistoryId'] ?? 0,
            'operation'                => 'Payment',
            'ticket_cost'              => $ticketCost,
            'permit_fees'              => $permitFees,
            'payment_type'             => $paymentVia,
            'payment_method'           => $paymentMethod,
            'transaction_id'           => $transactionId,
            'is_processed'             => 1,
            'details'                  => json_encode($options, JSON_NUMERIC_CHECK),
            'operation_at'             => mysqlDT(),
            'operation_by'             => $affiliateId,
            'operation_by_user_type'   => 'Affiliate',
            'updated_at'               => Carbon::now(),
            'created_at'               => Carbon::now(),
        ]);

        DetectPayoutAdjustment::dispatch($bookingId, $historyId, 'Payment');

        return $historyId;
    }

    /**
     * Store a transaction record and return its ID.
     */
    public static function storePaymentInTransaction(
        int $bookingId,
        float $amount,
        float $feeAmount,
        string $via,
        string $status,
        int $customerId,
        string $requestFrom
    ): int {
        return DB::table('transactions')->insertGetId([
            'tour_booking_id'        => $bookingId,
            'amount'                 => $amount,
            'fee_amount'             => $feeAmount,
            'via'                    => $via,
            'response_init'          => '',
            'response_end'           => '',
            'response_end_datetime'  => now(),
            'status'                 => $status,
            'payment_type'           => 0,
            'visitor'                => request()->getClientIp(),
            'request_from'           => $requestFrom,
            'created_by'             => $customerId,
            'created_by_user_type'   => 'Affiliate',
            'created_at'             => now()
        ]);
    }
}
