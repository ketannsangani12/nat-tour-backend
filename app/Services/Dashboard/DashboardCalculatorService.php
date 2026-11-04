<?php

namespace App\Services\Dashboard;

use App\Services\Booking\RemainingPayment\UnpaidPaxService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class DashboardCalculatorService
{
    private const US_ALIASES = ['United States', 'United States of America', 'USA', 'US'];

    private const EUROPEAN_COUNTRIES = [
        'France','Italy','Germany','Netherlands','United Kingdom','UK','Spain','Poland',
        'Malta','Switzerland','Belgium','Austria','Sweden','Norway','Denmark','Finland',
        'Portugal','Ireland','Greece','Czech Republic','Hungary','Romania','Croatia',
        'Slovakia','Slovenia','Bulgaria','Serbia','Estonia','Latvia','Lithuania',
        'Luxembourg','Iceland','Cyprus','Albania','Bosnia',
    ];

	public static function getDashboardRows($fromDate, $toDate, $tourPackageIds = [], $paymentTypes = [], $thirdPartyPlatforms = [], $requestFromValues = [], $bookingSources = [])
    {
        $tenantId    = getSubdomainFromHost();
        $fromDateStr = $fromDate->toDateString();
        $toDateStr   = $toDate->toDateString();

        $cacheKey = 'dashboard_rows:' . md5(implode('|', [
            $tenantId, $fromDateStr, $toDateStr,
            implode(',', $tourPackageIds),
            implode(',', $paymentTypes),
            implode(',', $thirdPartyPlatforms),
            implode(',', $requestFromValues),
            implode(',', $bookingSources),
        ]));

        // Fast path: return without acquiring a lock on cache hits.
        if ($data = Cache::get($cacheKey)) {
            return $data;
        }

        // Stampede guard: only one process computes; concurrent callers wait for
        // the lock and then read the result the first process cached.
        return Cache::lock('compute_lock:' . $cacheKey, 60)->block(55, function () use (
            $cacheKey, $tenantId, $fromDateStr, $toDateStr,
            $tourPackageIds, $paymentTypes, $thirdPartyPlatforms, $requestFromValues,
            $fromDate, $toDate, $bookingSources
        ) {
            // Double-checked locking: another process may have populated cache
            // while we were waiting for the lock.
            if ($data = Cache::get($cacheKey)) {
                return $data;
            }

            // Drive the tbt derived table from tour_slot_times (most selective:
            // the date range) so MySQL scans tst → tbm → tbt in index order rather
            // than scanning all of tour_bookings_transact and filtering via a large
            // IN list.  Derived tables are materialised before the outer WHERE is
            // applied, so the join order inside them matters.
            if ($tenantId === 'dixies') {
                $tbtSubquery = DB::raw("
                    (
                        SELECT
                            tbt_inner.tour_booking_id,
                            SUM(tbt_inner.tourists * tbt_inner.additional_charge) as total_tax,
                            SUM(
                                CASE
                                    WHEN LOWER(IFNULL(tpr.rate_for, '')) LIKE '%children%' THEN 0
                                    ELSE GREATEST(0,
                                        CAST(tbt_inner.tourists AS SIGNED) - CASE
                                            WHEN tbt_inner.check_in_status IS NULL OR tbt_inner.check_in_status = '' OR tbt_inner.check_in_status = 'null'
                                            THEN 0
                                            ELSE IFNULL(JSON_LENGTH(JSON_SEARCH(tbt_inner.check_in_status, 'all', 'A', NULL, '\$[*].status')), 0)
                                        END
                                    ) * tbt_inner.permit_fee
                                END
                            ) as total_permit_fee
                        FROM tour_slot_times tst_inner
                        INNER JOIN tour_bookings_master tbm_inner ON tbm_inner.tour_slot_id = tst_inner.id
                        INNER JOIN tour_bookings_transact tbt_inner ON tbt_inner.tour_booking_id = tbm_inner.id
                        LEFT JOIN tour_package_rates tpr ON tpr.id = tbt_inner.tour_package_rate_id
                        WHERE tst_inner.date BETWEEN '{$fromDateStr}' AND '{$toDateStr}'
                        AND (tbt_inner.deleted_by IS NULL OR tbt_inner.deleted_by = 0)
                        GROUP BY tbt_inner.tour_booking_id
                    ) as tbt
                ");
            } else {
                $tbtSubquery = DB::raw("
                    (
                        SELECT
                            tbt_inner.tour_booking_id,
                            SUM(tbt_inner.tourists * tbt_inner.additional_charge) as total_tax,
                            SUM(
                                GREATEST(0,
                                    CAST(tbt_inner.tourists AS SIGNED) - CASE
                                        WHEN tbt_inner.check_in_status IS NULL OR tbt_inner.check_in_status = '' OR tbt_inner.check_in_status = 'null'
                                        THEN 0
                                        ELSE IFNULL(JSON_LENGTH(JSON_SEARCH(tbt_inner.check_in_status, 'all', 'A', NULL, '\$[*].status')), 0)
                                    END
                                ) * tbt_inner.permit_fee
                            ) as total_permit_fee
                        FROM tour_slot_times tst_inner
                        INNER JOIN tour_bookings_master tbm_inner ON tbm_inner.tour_slot_id = tst_inner.id
                        INNER JOIN tour_bookings_transact tbt_inner ON tbt_inner.tour_booking_id = tbm_inner.id
                        WHERE tst_inner.date BETWEEN '{$fromDateStr}' AND '{$toDateStr}'
                        AND (tbt_inner.deleted_by IS NULL OR tbt_inner.deleted_by = 0)
                        GROUP BY tbt_inner.tour_booking_id
                    ) as tbt
                ");
            }

            $query = DB::table('tour_bookings_master as tbm')
                ->join('transactions as tra', 'tra.tour_booking_id', '=', 'tbm.id')
                ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
                ->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
                ->join('customers as c', 'c.id', '=', 'tbm.customer_id')
                ->Join($tbtSubquery, 'tbt.tour_booking_id', '=', 'tbm.id')
                ->select([
                    'tra.tour_booking_id',
                    'tra.amount',
                    'tra.via',
                    'tra.fee_amount',
                    'tra.status',
                    'tbm.status as booking_status',
                    'tbm.discount2_value as discount',
                    'tbm.tour_package_id',
                    'tbm.tourists',
                    'tbm.infants',
                    'tst.date',
                    'tp.name as package_name',
                    'tst.time_from',
                    'tra.created_at',
                    'tbt.total_tax',
                    'tbt.total_permit_fee',
                    'c.name as customer_name',
                    'c.id as customer_id',
                    'c.status as customer_status',
                    'c.is_affiliate',
                    'c.country',
                    'c.region',
                    'c.city',
                    DB::raw('(IFNULL(tbm.tourists, 0) + IFNULL(tbm.infants, 0)) as total_pax'),
                ])
                ->where('tra.status', '!=', 'Failed')
                ->where('tst.deleted_by', '=', '0')
                ->whereIn('tra.request_from', $requestFromValues)
                ->where(function ($q) use ($paymentTypes, $thirdPartyPlatforms) {
                    $viaFilter = self::resolveViaFilter($paymentTypes, $thirdPartyPlatforms);
                    if (!empty($viaFilter)) {
                        $q->whereIn('tra.via', $viaFilter);
                    } else {
                        $q->whereNotIn('tra.via', ['Bokun']);
                    }
                })
                ->whereBetween('tst.date', [
                    $fromDate->toDateString(),
                    $toDate->toDateString()
                ]);

            if (!empty($tourPackageIds)) {
                $query->whereIn('tbm.tour_package_id', $tourPackageIds);
            }

            if (!empty($bookingSources)) {
                $query->whereIn('tbm.booking_source', $bookingSources);
            }

            $data = $query->orderBy('tst.date')->get();
            Cache::put($cacheKey, $data, 120);
            return $data;
        }); // Cache::lock->block
    }

    /**
     * Merges payment types and third-party platforms into a single via-filter list.
     * An empty result means no specific filter is selected, so Bokun should be excluded instead.
     */
    public static function resolveViaFilter(array $paymentTypes, array $thirdPartyPlatforms): array
    {
        return array_merge($paymentTypes, $thirdPartyPlatforms);
    }

    public static function getPurchaseDate($fromDate, $toDate, $tourPackageIds = [], $paymentTypes = [], $thirdPartyPlatforms = [], $requestFromValues = [], $bookingSources = [])
    {
        $query = DB::table('tour_bookings_master as tbm')
            ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->join('transactions as tra', 'tra.tour_booking_id', '=', 'tbm.id')
            ->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->select([
                'tra.tour_booking_id',
                'tra.amount',
                'tra.via',
                'tra.fee_amount',
                'tra.status',
                'tbm.discount2_value as discount',
                'tbm.tour_package_id',
                'tbm.tourists',
                'tbm.infants',
                'tst.date',
                'tp.name as package_name',
                'tst.time_from',
                'tra.created_at',
            ])
            ->where('tra.status', '!=', 'Failed')
            ->where('tst.deleted_by', '=', '0')
            ->whereIn('tra.request_from', $requestFromValues)
            ->where(function ($q) use ($paymentTypes, $thirdPartyPlatforms) {
                $viaFilter = self::resolveViaFilter($paymentTypes, $thirdPartyPlatforms);
                if (!empty($viaFilter)) {
                    $q->whereIn('tra.via', $viaFilter);
                } else {
                    $q->whereNotIn('tra.via', ['Bokun']);
                }
            })
            ->whereBetween('tra.created_at', [
                $fromDate->startOfDay(),
                $toDate->endOfDay()
            ]);

        if (!empty($tourPackageIds)) {
            $query->whereIn('tbm.tour_package_id', $tourPackageIds);
        }

        if (!empty($bookingSources)) {
            $query->whereIn('tbm.booking_source', $bookingSources);
        }

        return $query->orderBy('tra.created_at')->get();
    }
    public static function percentageChange($current, $previous)
    {
        if ($previous == 0) return null;
        return round((($current - $previous) / $previous) * 100, 1);
    }

    public static function aggregateBookings($rows)
    {
        $bookings = [];

        foreach ($rows as $row) {

            $id = $row->tour_booking_id;
            
            if (!isset($bookings[$id])) {
                $bookings[$id] = [
                    'date'               => $row->date,
                    'time'               => $row->time_from,
                    'package'            => $row->package_name,
                    'amount'             => 0,
                    'refund'             => 0,
                    'fee_amount'         => 0,
                    'refund_fee_amount'  => 0,
                    'refund_remove_fee'  => 0,
                    'total_tax'          => $row->total_tax ?? 0,
                    'total_permit_fee'   => $row->total_permit_fee ?? 0,
                    'discount'           => $row->discount ?? 0,
                    'tourists'           => ($row->tourists ?? 0) + ($row->infants ?? 0),
                    'infants'            => $row->infants ?? 0,
                    'via'                => $row->via,
                    'total_pax'          => (int) ($row->total_pax ?? 0),
                    // True only when at least one Completed or Refund transaction exists.
                    // Initialized-only bookings (abandoned payments) are excluded from
                    // tourist, tax, and permit-fee totals.
                    'has_payment'        => false,
                ];
            }

            if ($row->status === 'Completed') {
                $bookings[$id]['has_payment'] = true;
                $bookings[$id]['amount'] += $row->amount;
                $bookings[$id]['fee_amount'] += $row->fee_amount;
            }

            if ($row->status === 'Refund') {
                $bookings[$id]['has_payment'] = true;
                $bookings[$id]['refund'] += $row->amount;
                $bookings[$id]['refund_fee_amount'] += $row->fee_amount;
            }
        }

        // Final calculation
        foreach ($bookings as &$b) {
            $b['refund_remove_fee'] = $b['refund'] - $b['refund_fee_amount'];
            $b['net'] = $b['amount'] - $b['refund_remove_fee'] - $b['fee_amount'];
        }

        // Subtract pax added via Change People whose payment link is still Unpaid —
        // they must not appear in tourist, permit-fee, or tax totals.
        $bookingIds = array_keys($bookings);
        if (!empty($bookingIds)) {
            try {
                $unpaidByTransact = UnpaidPaxService::getUnpaidPaxByTransactId($bookingIds);
                $unpaidByBooking  = [];

                // Look up permit_fee and additional_charge (state tax) per transact row
                // so we can deduct the correct permit-fee and tax amounts per booking.
                $permitFeeDeduction = [];
                $taxDeduction       = [];
                if (!empty($unpaidByTransact)) {
                    $tbtRows = DB::table('tour_bookings_transact as tbt')
                        ->whereIn('tbt.id', array_keys($unpaidByTransact))
                        ->select('tbt.id', 'tbt.tour_booking_id', 'tbt.permit_fee', 'tbt.additional_charge')
                        ->get();
                    foreach ($tbtRows as $tbt) {
                        $cnt = $unpaidByTransact[(int)$tbt->id] ?? 0;
                        $bid = (int)$tbt->tour_booking_id;
                        $unpaidByBooking[$bid]    = ($unpaidByBooking[$bid]    ?? 0) + $cnt;
                        $permitFeeDeduction[$bid] = ($permitFeeDeduction[$bid] ?? 0) + $cnt * (float)$tbt->permit_fee;
                        $taxDeduction[$bid]       = ($taxDeduction[$bid]       ?? 0) + $cnt * (float)$tbt->additional_charge;
                    }
                }

                // Apply deductions
                foreach ($unpaidByBooking as $bid => $unpaidCount) {
                    if (isset($bookings[$bid])) {
                        $bookings[$bid]['tourists']         = max(0, $bookings[$bid]['tourists']         - $unpaidCount);
                        $bookings[$bid]['total_permit_fee'] = max(0, $bookings[$bid]['total_permit_fee'] - ($permitFeeDeduction[$bid] ?? 0));
                        $bookings[$bid]['total_tax']        = max(0, $bookings[$bid]['total_tax']        - ($taxDeduction[$bid]       ?? 0));
                    }
                }
            } catch (\Exception $e) {
                Log::warning('aggregateBookings: unpaid pax subtraction failed: ' . $e->getMessage());
            }
        }

        return $bookings;
    }

    public static function calculateFromAggregated($bookings)
    {
        $totals = [
            'gross'     => 0,
            'net'       => 0,
            'refund'    => 0,
            'fees'      => 0,
            'discount'  => 0,
            'tax'       => 0,
            'permit_fee'=> 0,
            'tourists'  => 0,
            'bookings'  => count($bookings),
        ];

        foreach ($bookings as $b) {

            $totals['gross']    += $b['amount'];
            $totals['refund']   += $b['refund_remove_fee'];
            $totals['fees']     += $b['fee_amount'];
            $totals['discount'] += $b['discount'];
            $totals['net']      += ($b['amount'] - $b['refund_remove_fee'] - $b['fee_amount']);

            // Only count pax-based metrics for bookings with actual payment collected.
            // Initialized-only (abandoned) bookings are excluded.
            if ($b['has_payment'] ?? false) {
                $totals['tax']        += $b['total_tax'];
                $totals['permit_fee'] += $b['total_permit_fee'];
                $totals['tourists']   += $b['tourists'];
            }
        }

        return [
            'total_gross'    => round($totals['gross'], 2),
            'total_net'      => round($totals['net'], 2),
            'total_refund'   => round($totals['refund'], 2),
            'total_discount' => round($totals['discount'], 2),
            'total_fees'     => round($totals['fees'], 2),
            'total_tax'        => round($totals['tax'], 2),
            'total_permit_fee' => round($totals['permit_fee'], 2),
            'total_tourists' => $totals['tourists'],
            'total_bookings' => $totals['bookings'],
        ];
    }

    public static function getDailyNetByDate($bookings)
    {
        $grouped = [];

        foreach ($bookings as $b) {
            $date = Carbon::parse($b['date'])->format('M j');

            if (!isset($grouped[$date])) {
                $grouped[$date] = ['booked' => 0, 'refund' => 0];
            }

            $grouped[$date]['booked'] += $b['net'];
            $grouped[$date]['refund'] += $b['refund_remove_fee'];
        }

        return [
            'labels' => array_keys($grouped),
            'booked' => array_column($grouped, 'booked'),
            'refund' => array_column($grouped, 'refund'),
        ];
    }

    public static function getNetAndBookingsByPackage($bookings)
    {
        $grouped = [];

        foreach ($bookings as $b) {
            $pkg = $b['package'];

            if (!isset($grouped[$pkg])) {
                $grouped[$pkg] = [
                    'net' => 0,
                    'refund' => 0,
                    'count' => 0
                ];
            }

            $grouped[$pkg]['net'] += $b['net'];
            $grouped[$pkg]['refund'] += $b['refund'];
            $grouped[$pkg]['count']++;
        }

        // Sort by net DESC
        uasort($grouped, fn($a, $b) => $b['net'] <=> $a['net']);

        return [
            'labels' => array_values(array_keys($grouped)), 
            'net' => array_values(array_map(fn($g) => round($g['net'], 2), $grouped)),
            'refund' => array_values(array_map(fn($g) => round($g['refund'], 2), $grouped)),
            'bookings' => array_values(array_column($grouped, 'count')),
        ];
    }

    public static function getPeakBookingTimes($bookings)
    {
        $grouped = [];

        foreach ($bookings as $b) {

            if (empty($b['time'])) {
                continue;
            }

            // Combine date + time for a unique key so we preserve the date
            $date    = !empty($b['date']) ? Carbon::parse($b['date'])->format('Y-m-d') : Carbon::today()->format('Y-m-d');
            $timeKey = Carbon::parse($b['time'])->format('H:i:s');
            $key     = $date . ' ' . $timeKey;

            if (!isset($grouped[$key])) {
                $grouped[$key] = ['date' => $date, 'time' => $timeKey, 'count' => 0];
            }

            // Each booking counted once
            $grouped[$key]['count']++;
        }

        // Sort by datetime key
        ksort($grouped);

        // Format for display (12h time, readable date)
        return collect($grouped)->map(function ($entry) {
            return [
                'booking_time'  => Carbon::parse($entry['time'])->format('h:i A'),
                'booking_date'  => Carbon::parse($entry['date'])->format('M j'),
                'booking_count' => $entry['count'],
            ];
        })->values();
    }
    public static function getMostProfitableDays($bookings)
    {
        $grouped = $bookings;

        $days = [];

        foreach ($grouped as $g) {

            $ts       = strtotime($g['date']);
            $dayName  = date('l', $ts);
            $dayOrder = date('w', $ts); // 0=Sun

            $net = $g['net'];

            $key = $dayOrder;

            if (!isset($days[$key])) {
                $days[$key] = [
                    'day_name' => $dayName,
                    'day_order' => $dayOrder,
                    'net_total' => 0
                ];
            }

            $days[$key]['net_total'] += $net;
        }

        // 3) Sort by day order
        usort($days, function ($a, $b) {
            return $a['day_order'] <=> $b['day_order'];
        });

        return array_values($days);
    }
    public static function getWeekendVsWeekday($bookings)
    {
        $grouped = $bookings;

        $weeks = [];

        foreach ($grouped as $g) {

            $ts         = strtotime($g['date']);
            $weekNumber = date('W', $ts);
            $dayOfWeek  = date('w', $ts); // 0=Sun 6=Sat

            $weekStart = date('Y-m-d', strtotime('monday this week', $ts));
            $weekEnd   = date('Y-m-d', strtotime('sunday this week', $ts));

            $net = $g['net'];

            if (!isset($weeks[$weekNumber])) {
                $weeks[$weekNumber] = [
                    'week_number' => $weekNumber,
                    'week_start' => $weekStart,
                    'week_end' => $weekEnd,
                    'weekend_total' => 0,
                    'weekday_total' => 0
                ];
            }

            if ($dayOfWeek == 0 || $dayOfWeek == 6) {
                $weeks[$weekNumber]['weekend_total'] += $net;
            } else {
                $weeks[$weekNumber]['weekday_total'] += $net;
            }
        }

        ksort($weeks);

        return array_values($weeks);
    }
    public static function getNetByPaymentDate($rows, $fromDate, $toDate)
    {
        $grouped = [];

        foreach ($rows as $row) {

            // Skip failed
            if ($row->status === 'Failed') {
                continue;
            }

            // Payment date
            $dateKey = Carbon::parse($row->created_at)->format('Y-m-d');

            if (!isset($grouped[$dateKey])) {
                $grouped[$dateKey] = [
                    'total_amount' => 0,
                    'total_fee' => 0,
                ];
            }

            if ($row->status === 'Completed') {
                $grouped[$dateKey]['total_amount'] += $row->amount;
                $grouped[$dateKey]['total_fee'] += $row->fee_amount;
            }

            if ($row->status === 'Refund') {
                if ($row->amount == 0) {
                    $grouped[$dateKey]['total_amount'] -= $row->fee_amount;
                } else {
                    $grouped[$dateKey]['total_amount'] -= $row->amount;
                }
            }
        }

        // Fill missing dates
        $period = CarbonPeriod::create($fromDate, $toDate);

        $chartData = [
            'labels' => [],
            'values' => []
        ];

        foreach ($period as $date) {

            $key = $date->format('Y-m-d');

            $totalAmount = $grouped[$key]['total_amount'] ?? 0;
            $totalFee    = $grouped[$key]['total_fee'] ?? 0;

            $net = $totalAmount - $totalFee;

            $chartData['labels'][] = $date->format('M d');
            $chartData['values'][] = round($net, 2);
        }

        return $chartData;
    }

    /**
     * Tourists & Bookings grouped by date range smartly:
     * @param  array  $bookings  Output of aggregateBookings()
     * @param  Carbon $fromDate
     * @param  Carbon $toDate
     * @return array { labels[], tourists[], bookings[], group_by }
     */
    public static function getTouristsAndBookings(array $bookings, Carbon $fromDate, Carbon $toDate): array
    {
        $diffDays = $fromDate->diffInDays($toDate);

        // Decide grouping strategy
        if ($diffDays <= 90) {
            $groupBy = 'date';
        } else {
            $groupBy = 'month';
        }

        $grouped = [];

        foreach ($bookings as $b) {
            $date = Carbon::parse($b['date']);

            $key = match ($groupBy) {
                'date'  => $date->format('Y-m-d'),      // "2026-03-05"
                'month' => $date->format('Y-m'),        // "2026-03"
            };

            if (!isset($grouped[$key])) {
                $grouped[$key] = ['tourists' => 0, 'bookings' => 0];
            }

            if ($b['has_payment'] ?? false) {
                $grouped[$key]['tourists'] += $b['tourists'];
            }
            $grouped[$key]['bookings']++;
        }

        // Build full period so gaps are filled with 0
        $labels        = [];
        $tourists      = [];
        $bookingCounts = [];
   
        if ($groupBy === 'date') {

            $period = CarbonPeriod::create($fromDate->copy()->startOfDay(), '1 day', $toDate->copy()->startOfDay());

            foreach ($period as $day) {
                $key             = $day->format('Y-m-d');
                $labels[]        = $day->format('M j');          // "Apr 5"
                $tourists[]      = $grouped[$key]['tourists'] ?? 0;
                $bookingCounts[] = $grouped[$key]['bookings'] ?? 0;
            }

        } else {

            $period = CarbonPeriod::create(
                $fromDate->copy()->startOfMonth(),
                '1 month',
                $toDate->copy()->startOfMonth()
            );

            foreach ($period as $month) {
                $key             = $month->format('Y-m');
                $labels[]        = $month->format('M, Y');       // "Jan, 2025"
                $tourists[]      = $grouped[$key]['tourists'] ?? 0;
                $bookingCounts[] = $grouped[$key]['bookings'] ?? 0;
            }
        }

        return [
            'labels'   => $labels,
            'tourists' => $tourists,
            'bookings' => $bookingCounts,
            'group_by' => $groupBy,   // useful for JS tooltip formatting
        ];
    }

    /**
     * State Tax grouped by tour date (for line chart).
     *
     * @param  array  $bookings  Output of aggregateBookings()
     * @param  Carbon $fromDate
     * @param  Carbon $toDate
     * @return array { labels[], values[] }
     */
    public static function getStateTaxByDate(array $bookings, Carbon $fromDate, Carbon $toDate): array
    {
        $grouped = [];

        foreach ($bookings as $b) {
            $key = Carbon::parse($b['date'])->format('Y-m-d');

            if (!isset($grouped[$key])) {
                $grouped[$key] = 0;
            }

            $grouped[$key] += $b['total_tax'];
        }

        // Fill every date in range so gaps show as 0
        $period  = CarbonPeriod::create($fromDate->copy()->startOfDay(), '1 day', $toDate->copy()->startOfDay());
        $labels  = [];
        $values  = [];

        foreach ($period as $day) {
            $key      = $day->format('Y-m-d');
            $labels[] = $day->format('M j');
            $values[] = round($grouped[$key] ?? 0, 2);
        }

        return compact('labels', 'values');
    }

    /**
     * Average State Tax per person, grouped by package (for bar chart).
     * avg = total_tax / tourists  (tourists already includes infants)
     *
     * @param  array $bookings  Output of aggregateBookings()
     * @return array { labels[], values[] }
     */
    public static function getAvgTaxPerPersonByPackage(array $bookings): array
    {
        $grouped = [];   // [ package => [ tax => float, tourists => int ] ]

        foreach ($bookings as $b) {
            $pkg = $b['package'];

            if (!isset($grouped[$pkg])) {
                $grouped[$pkg] = ['tax' => 0, 'tourists' => 0];
            }

            if ($b['has_payment'] ?? false) {
                $grouped[$pkg]['tax']      += $b['total_tax'];
                $grouped[$pkg]['tourists'] += $b['tourists'] - $b['infants'];
            }
        }

        // Sort by avg DESC
        uasort($grouped, function ($a, $b) {
            $avgA = $a['tourists'] > 0 ? $a['tax'] / $a['tourists'] : 0;
            $avgB = $b['tourists'] > 0 ? $b['tax'] / $b['tourists'] : 0;
            return $avgB <=> $avgA;
        });

        $labels = [];
        $values = [];

        foreach ($grouped as $pkg => $data) {
            $labels[] = $pkg;
            $values[] = $data['tourists'] > 0
                ? round($data['tax'] / $data['tourists'], 2)
                : 0;
        }

        return compact('labels', 'values');
    }

    public static function getAffiliateSummary($rows, $prevRows = null): array
    {
        // ─── Current period (reuse already-fetched rows, no extra DB query)
        $affiliateRows = $rows->filter(fn($r) => isset($r->is_affiliate) && $r->is_affiliate === 'Yes');
        $currentData   = self::aggregateAffiliates($affiliateRows);

        // ─── Previous period (only when month selected)
        $prevData = [];
        if ($prevRows !== null) {
            $prevAffiliateRows = $prevRows->filter(fn($r) => isset($r->is_affiliate) && $r->is_affiliate === 'Yes');
            $prevData          = self::aggregateAffiliates($prevAffiliateRows);
        }

        // ─── Build final result
        $result = [];

        foreach ($currentData as $cid => $curr) {
            $prevGross = $prevData[$cid]['total_gross'] ?? null;

            $result[] = [
                'affiliate_name'  => $curr['affiliate_name'],
                'status'          => $curr['status'],
                'total_bookings'  => $curr['total_bookings'],
                'total_gross'     => $curr['total_gross'],
                'top_tour'        => $curr['top_tour'],
                'change_percent'  => $prevGross !== null
                                        ? self::percentageChange($curr['total_gross'], $prevGross)
                                        : null,
            ];
        }

        // Sort by total_gross DESC
        usort($result, fn($a, $b) => $b['total_gross'] <=> $a['total_gross']);

        return $result;
    }

    // ─── Affiliate summary helper
    public static function aggregateAffiliates($rows): array
    {
        $affiliates = [];

        foreach ($rows as $row) {

            $cid = $row->customer_id ?? $row->customer_name; // fallback if no customer_id in select

            if (!isset($affiliates[$cid])) {
                $affiliates[$cid] = [
                    'affiliate_name' => $row->customer_name,
                    'status'         => isset($row->customer_status)
                                            ? (strtolower($row->customer_status) === 'active' ? 'active' : 'inactive')
                                            : 'active',
                    'bookings'       => [],   // unique booking IDs
                    'gross'          => 0,
                    'package_counts' => [],   // for top tour
                ];
            }

            $bid = $row->tour_booking_id;

            // Count each booking once for bookings count & top tour
            if (!isset($affiliates[$cid]['bookings'][$bid])) {
                $affiliates[$cid]['bookings'][$bid] = true;

                $pkg = $row->package_name;
                $affiliates[$cid]['package_counts'][$pkg] =
                    ($affiliates[$cid]['package_counts'][$pkg] ?? 0) + 1;
            }

            // Revenue
            if ($row->status === 'Completed') {
                $affiliates[$cid]['gross'] += $row->amount;
            }

            if ($row->status === 'Refund') {
                $affiliates[$cid]['gross'] -= $row->amount;
            }
        }

        // ─── Flatten each affiliate ────────────────────────────────────
        foreach ($affiliates as &$a) {
            $a['total_bookings'] = count($a['bookings']);
            $a['total_gross']    = round($a['gross'], 2);

            if (!empty($a['package_counts'])) {
                arsort($a['package_counts']);
                $a['top_tour'] = array_key_first($a['package_counts']);
            } else {
                $a['top_tour'] = '-';
            }

            unset($a['bookings'], $a['package_counts'], $a['gross']);
        }

        return $affiliates;
    }

    public static function getPackagePerformance(array $bookings): array
    {
        $packages = [];

        foreach ($bookings as $b) {
            $pkg = $b['package'];

            if (!isset($packages[$pkg])) {
                $packages[$pkg] = [
                    'package'    => $pkg,
                    'net'        => 0,
                    'gross'      => 0,
                    'refund'     => 0,
                    'fee'        => 0,
                    'tax'        => 0,
                    'tourists'   => 0,
                    'bookings'   => 0,
                ];
            }

            $packages[$pkg]['gross']    += $b['amount'];
            $packages[$pkg]['refund']   += $b['refund'];
            $packages[$pkg]['fee']      += $b['fee_amount'];
            $packages[$pkg]['net']      += $b['net'];
            $packages[$pkg]['bookings']++;
            if ($b['has_payment'] ?? false) {
                $packages[$pkg]['tax']      += $b['total_tax'];
                $packages[$pkg]['tourists'] += $b['tourists'] - $b['infants'];
            }
        }

        $result = [];

        foreach ($packages as $p) {

            $avgPerPerson = $p['tourists'] > 0
                ? round($p['net'] / $p['tourists'], 2)
                : 0;

            // Badge logic based on net revenue relative to others
            $result[] = [
                'package'        => $p['package'],
                'net'            => round($p['net'], 2),
                'gross'          => round($p['gross'], 2),
                'refund'         => round($p['refund'], 2),
                'tax'            => round($p['tax'], 2),
                'bookings'       => $p['bookings'],
                'tourists'       => $p['tourists'],
                'avg_per_person' => $avgPerPerson,
            ];
        }

        // Sort by net DESC
        usort($result, fn($a, $b) => $b['net'] <=> $a['net']);

       // Assign badges based on bookings count rank
        $total = count($result);

        if ($total > 0) {
            // Get booking counts sorted DESC to determine rank
            $sortedByBookings = $result;
            usort($sortedByBookings, fn($a, $b) => $b['bookings'] <=> $a['bookings']);

            $maxBookings = $sortedByBookings[0]['bookings'];
            $minBookings = $sortedByBookings[$total - 1]['bookings'];
            $range       = $maxBookings - $minBookings;
            
            foreach ($result as &$r) {
                $bookings = $r['bookings'];

                if ($total === 1) {
                    $r['badge'] = 'good';
                } elseif ($range === 0) {
                    // All packages have equal bookings
                    $r['badge'] = 'good';
                } else {
                    // Normalize 0–1 based on position between min and max
                    $score = ($bookings - $minBookings) / $range;

                    if ($score >= 0.66) {
                        $r['badge'] = 'excellent';
                    } elseif ($score >= 0.33) {
                        $r['badge'] = 'good';
                    } else {
                        $r['badge'] = 'average';
                    }
                }
            }
        }

        return $result;

    }

    public static function getPaymentAndGroupsData($fromDate, $toDate, $tourPackageIds = [], $paymentTypes = [], $thirdPartyPlatforms = [], $requestFromValues = [], $bookingSources = [])
    {
        $rows     = self::getDashboardRows($fromDate, $toDate, $tourPackageIds, $paymentTypes, $thirdPartyPlatforms, $requestFromValues, $bookingSources);
        $bookings = self::aggregateBookings($rows);
        return self::computePaymentAndGroups($bookings);
    }

    public static function computePaymentAndGroups(array $bookings): array
    {
        $segmentRanges = [
            'Solo(1)'     => [1, 1],
            'Pair(2)'     => [2, 2],
            'Small(3-4)'  => [3, 4],
            'Medium(5-6)' => [5, 6],
            'Group(7-10)' => [7, 10],
            'Large(10+)'  => [11, PHP_INT_MAX],
        ];

        $paymentTypes = [];
        $groupData    = array_fill_keys(array_keys($segmentRanges), ['bookings' => 0, 'revenue' => 0.0, 'tourists' => 0]);

        // Single pass: accumulate payment types and group segments together
        foreach ($bookings as $b) {
            $net = $b['net'];
            $via = $b['via'];

            if ($net > 0) {
                if (!isset($paymentTypes[$via])) {
                    $paymentTypes[$via] = ['bookings' => 0, 'revenue' => 0.0];
                }
                $paymentTypes[$via]['bookings']++;
                $paymentTypes[$via]['revenue'] += $net;
            }

            $pax = $b['total_pax'];
            if ($pax > 0) {
                foreach ($segmentRanges as $label => [$min, $max]) {
                    if ($pax >= $min && $pax <= $max) {
                        $groupData[$label]['bookings']++;
                        $groupData[$label]['revenue']  += $net;
                        $groupData[$label]['tourists'] += $b['tourists'];
                        break;
                    }
                }
            }
        }

        $totalBookings = array_sum(array_column($groupData, 'bookings'));
        $totalRevenue  = array_sum(array_column($paymentTypes, 'revenue'));
        $totalTourists = array_sum(array_column($groupData, 'tourists'));

        // Single pass over the 6 segments: KPIs + breakdown + rev-per-tourist range
        $mostCommonLabel    = '';
        $mostCommonBookings = 0;
        $topRevLabel        = '';
        $topRevAmount       = 0;
        $premiumLabel       = '';
        $premiumAvg         = 0;
        $premiumBookings    = 0;
        $revPerTouristMin   = PHP_INT_MAX;
        $revPerTouristMax   = 0;
        $breakdown          = [];

        foreach ($groupData as $label => $d) {
            if ($d['bookings'] > $mostCommonBookings) {
                $mostCommonBookings = $d['bookings'];
                $mostCommonLabel    = $label;
            }
            if ($d['revenue'] > $topRevAmount) {
                $topRevAmount = $d['revenue'];
                $topRevLabel  = $label;
            }
            if ($d['bookings'] > 0) {
                $avg = $d['revenue'] / $d['bookings'];
                if ($avg > $premiumAvg) {
                    $premiumAvg      = $avg;
                    $premiumLabel    = $label;
                    $premiumBookings = $d['bookings'];
                }
            }
            if ($d['tourists'] > 0 && $d['revenue'] > 0) {
                $rpt = $d['revenue'] / $d['tourists'];
                if ($rpt < $revPerTouristMin) $revPerTouristMin = $rpt;
                if ($rpt > $revPerTouristMax) $revPerTouristMax = $rpt;
            }

            $avgPerBooking = $d['bookings'] > 0 ? $d['revenue'] / $d['bookings'] : 0;
            $revPerTourist = $d['tourists'] > 0 ? $d['revenue'] / $d['tourists'] : 0;
            $breakdown[] = [
                'label'           => $label,
                'bookings'        => $d['bookings'],
                'pct_bookings'    => $totalBookings > 0 ? round(($d['bookings'] / $totalBookings) * 100, 1) : 0,
                'revenue'         => round($d['revenue'], 2),
                'pct_revenue'     => $totalRevenue > 0 ? round(($d['revenue'] / $totalRevenue) * 100, 1) : 0,
                'tourists'        => $d['tourists'],
                'avg_per_booking' => round($avgPerBooking, 0),
                'rev_per_tourist' => round($revPerTourist, 2),
            ];
        }

        $mostCommonPct = $totalBookings > 0 ? round(($mostCommonBookings / $totalBookings) * 100, 1) : 0;
        $topRevPct     = $totalRevenue > 0 ? round(($topRevAmount / $totalRevenue) * 100, 1) : 0;
        if ($revPerTouristMin === PHP_INT_MAX) $revPerTouristMin = 0;

        // Insight bullets
        $insights = [];
        if ($topRevLabel) {
            $topBkPct = $totalBookings > 0
                ? round(($groupData[$topRevLabel]['bookings'] / $totalBookings) * 100, 1) : 0;
            $insights[] = "{$topRevLabel} groups generate the most revenue at \$" .
                self::formatCurrencyShort($topRevAmount) . " — {$topRevPct}% of total from {$topBkPct}% of bookings";
        }
        if ($premiumLabel) {
            $pairAvg       = isset($groupData['Pair(2)']) && $groupData['Pair(2)']['bookings'] > 0
                ? $groupData['Pair(2)']['revenue'] / $groupData['Pair(2)']['bookings'] : 0;
            $multiple      = $pairAvg > 0 ? round($premiumAvg / $pairAvg, 0) : '—';
            $premiumAvgFmt = number_format($premiumAvg, 0);
            $pairAvgFmt    = number_format($pairAvg, 0);
            $insights[] = "{$premiumLabel} average \${$premiumAvgFmt} per booking — {$multiple}× the pair average of \${$pairAvgFmt}";
        }
        if ($totalTourists > 0) {
            $spread      = $revPerTouristMax > 0 ? ($revPerTouristMax - $revPerTouristMin) / $revPerTouristMax : 0;
            $consistency = $spread < 0.3 ? 'remarkably consistent' : 'varied';
            $insights[]  = "Revenue per tourist is {$consistency} across all sizes — ranging \$" .
                number_format($revPerTouristMin, 2) . ' to $' . number_format($revPerTouristMax, 2);
        }
        if ($mostCommonLabel) {
            $insights[] = str_replace(['(', ')'], ['(', ' pax)'], $mostCommonLabel) .
                ' are the most frequent booking type with ' .
                number_format($mostCommonBookings) . ' transactions (' . $mostCommonPct . '% of all bookings)';
        }

        $totalAvgPerBooking = $totalBookings > 0 ? $totalRevenue / $totalBookings : 0;
        $totalRevTourists   = $totalTourists > 0 ? $totalRevenue / $totalTourists : 0;

        return [
            'kpis' => [
                'total_revenue'        => round($totalRevenue, 2),
                'revenue_pct'          => 100.0,
                'total_bookings'       => $totalBookings,
                'most_common_group'    => $mostCommonLabel,
                'most_common_bookings' => $mostCommonBookings,
                'most_common_pct'      => $mostCommonPct,
                'top_revenue_group'    => $topRevLabel,
                'top_revenue_amount'   => round($topRevAmount, 2),
                'top_revenue_pct'      => $topRevPct,
                'premium_group'        => $premiumLabel,
                'premium_avg'          => round($premiumAvg, 2),
                'premium_bookings'     => $premiumBookings,
            ],
            'chart_groups' => [
                'labels'   => array_keys($groupData),
                'bookings' => array_values(array_column($groupData, 'bookings')),
                'revenue'  => array_values(array_column($groupData, 'revenue')),
            ],
            'payment_types' => $paymentTypes,
            'breakdown'     => $breakdown,
            'breakdown_totals' => [
                'bookings'        => $totalBookings,
                'revenue'         => round($totalRevenue, 2),
                'tourists'        => $totalTourists,
                'avg_per_booking' => round($totalAvgPerBooking, 0),
                'rev_per_tourist' => round($totalRevTourists, 2),
            ],
            'insights' => $insights,
        ];
    }

    public static function formatCurrencyShort($amount): string
	{
	    if ($amount >= 1000000) {
	        return number_format($amount / 1000000, 2) . 'M';
	    }

	    if ($amount >= 1000) {
	        return number_format($amount / 1000, 1) . 'k';
	    }

	    return number_format($amount, 2);
	}

    // ── Customers Tab ──────────────────────────────────────────────────

    public static function getCustomersData($fromDate, $toDate, $tourPackageIds = [], $paymentTypes = [], $thirdPartyPlatforms = [], $requestFromValues = [], $bookingSources = [])
    {
        $rows     = self::getDashboardRows($fromDate, $toDate, $tourPackageIds, $paymentTypes, $thirdPartyPlatforms, $requestFromValues, $bookingSources);
        $bookings = self::aggregateBookings($rows);

        // Build booking → customer map from raw rows (one customer per booking)
        $customerByBooking = [];
        foreach ($rows as $row) {
            $id = $row->tour_booking_id;
            if (!isset($customerByBooking[$id])) {
                $customerByBooking[$id] = [
                    'id'   => $row->customer_id,
                    'name' => $row->customer_name,
                ];
            }
        }

        return self::computeCustomers($bookings, $customerByBooking);
    }

    public static function computeCustomers(array $bookings, array $customerByBooking): array
    {
        $segmentRanges = [
            'Solo(1)'     => [1, 1],
            'Pair(2)'     => [2, 2],
            'Small(3-4)'  => [3, 4],
            'Medium(5-6)' => [5, 6],
            'Group(7-10)' => [7, 10],
            'Large(10+)'  => [11, PHP_INT_MAX],
        ];

        $customers     = [];
        $totalRevenue  = 0.0;
        $totalTourists = 0;
        $repeatCount   = 0;
        $groupData     = array_fill_keys(array_keys($segmentRanges), ['bookings' => 0, 'revenue' => 0.0, 'tourists' => 0]);

        foreach ($bookings as $bookingId => $b) {
            if ($b['net'] <= 0) continue;

            $cInfo = $customerByBooking[$bookingId] ?? ['id' => 0, 'name' => 'Unknown'];
            $cId   = $cInfo['id'];

            if (!isset($customers[$cId])) {
                $customers[$cId] = ['name' => $cInfo['name'], 'bookings' => 0, 'revenue' => 0.0, 'tourists' => 0];
            }

            $customers[$cId]['bookings']++;
            $customers[$cId]['revenue']  += $b['net'];
            $customers[$cId]['tourists'] += $b['tourists'];

            // Count the moment a customer becomes a repeat (2nd booking)
            if ($customers[$cId]['bookings'] === 2) $repeatCount++;

            $totalRevenue  += $b['net'];
            $totalTourists += $b['tourists'];

            $pax = $b['total_pax'];
            if ($pax > 0) {
                foreach ($segmentRanges as $label => [$min, $max]) {
                    if ($pax >= $min && $pax <= $max) {
                        $groupData[$label]['bookings']++;
                        $groupData[$label]['revenue']  += $b['net'];
                        $groupData[$label]['tourists'] += $b['tourists'];
                        break;
                    }
                }
            }
        }

        if (empty($customers)) {
            return [
                'kpis' => [
                    'top_customer_name'     => '—',
                    'top_customer_revenue'  => 0,
                    'top_customer_bookings' => 0,
                    'top_customer_tourists' => 0,
                    'top3_revenue'          => 0,
                    'top3_names'            => '—',
                    'top3_pct'              => 0,
                    'repeat_customers'      => 0,
                    'total_customers'       => 0,
                    'avg_rev_per_tourist'   => 0,
                    'rev_per_tourist_min'   => 0,
                    'rev_per_tourist_max'   => 0,
                ],
                'chart_customers'  => ['labels' => [], 'revenue' => []],
                'chart_group_avg'  => ['labels' => array_keys($segmentRanges), 'avg' => array_fill(0, 6, 0)],
                'table_customers'  => [],
                'insights'         => [],
            ];
        }

        uasort($customers, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
        $customersArr = array_values($customers);

        $topCustomer = $customersArr[0];

        $top3Slice   = array_slice($customersArr, 0, 3);
        $top3Revenue = array_sum(array_column($top3Slice, 'revenue'));
        $top3Names   = implode(' + ', array_column($top3Slice, 'name'));
        $top3Pct     = $totalRevenue > 0 ? round(($top3Revenue / $totalRevenue) * 100, 1) : 0;

        $avgRevPerTourist = $totalTourists > 0 ? round($totalRevenue / $totalTourists, 2) : 0;

        // Single pass over 6 segments: rev-per-tourist range + chart avg values
        $rptMin         = PHP_INT_MAX;
        $rptMax         = 0;
        $groupAvgValues = [];

        foreach ($groupData as $d) {
            if ($d['tourists'] > 0 && $d['revenue'] > 0) {
                $rpt = $d['revenue'] / $d['tourists'];
                if ($rpt < $rptMin) $rptMin = $rpt;
                if ($rpt > $rptMax) $rptMax = $rpt;
            }
            $groupAvgValues[] = $d['bookings'] > 0 ? round($d['revenue'] / $d['bookings'], 0) : 0;
        }
        if ($rptMin === PHP_INT_MAX) $rptMin = 0;

        // Single pass over top-12: build chart arrays and table rows together
        $top12          = array_slice($customersArr, 0, 12);
        $chartLabels    = [];
        $chartRevenue   = [];
        $tableCustomers = [];

        foreach ($top12 as $c) {
            $revRounded     = round($c['revenue'], 2);
            $chartLabels[]  = $c['name'];
            $chartRevenue[] = $revRounded;
            $tableCustomers[] = [
                'name'            => $c['name'],
                'revenue'         => $revRounded,
                'bookings'        => $c['bookings'],
                'tourists'        => $c['tourists'],
                'avg_pax_booking' => $c['bookings'] > 0 ? round($c['tourists'] / $c['bookings'], 1) : 0,
                'rev_per_tourist' => $c['tourists'] > 0 ? round($c['revenue'] / $c['tourists'], 2) : 0,
            ];
        }

        // Insights
        $insights = [];

        $largeData = $groupData['Large(10+)'];
        $soloData  = $groupData['Solo(1)'];
        $largeAvg  = $largeData['bookings'] > 0 ? $largeData['revenue'] / $largeData['bookings'] : 0;
        $soloAvg   = $soloData['bookings']  > 0 ? $soloData['revenue']  / $soloData['bookings']  : 0;
        if ($largeAvg > 0 && $soloAvg > 0) {
            $multiple   = round($largeAvg / $soloAvg, 0);
            $insights[] = 'Large groups (10+ pax) average $' . number_format($largeAvg, 0) .
                ' per booking — ' . $multiple . '× higher than a solo booking ($' . number_format($soloAvg, 0) . ')';
        }

        if ($totalTourists > 0 && $rptMin > 0 && $rptMax > 0) {
            $spread      = $rptMax > 0 ? ($rptMax - $rptMin) / $rptMax : 0;
            $consistency = $spread < 0.3 ? 'remarkably stable' : 'varied';
            $insights[]  = 'Revenue per tourist is ' . $consistency . ' across all group sizes — ranging $' .
                number_format($rptMin, 2) . ' to $' . number_format($rptMax, 2);
        }

        if (!empty($top3Names) && $top3Pct > 0) {
            $insights[] = 'Top 3 customers (' . $top3Names . ') account for $' .
                self::formatCurrencyShort($top3Revenue) . ' — ' . $top3Pct . '% of total revenue';
        }

        if ($repeatCount > 0) {
            $totalCustCount = count($customersArr);
            $repeatPct      = $totalCustCount > 0 ? round(($repeatCount / $totalCustCount) * 100, 1) : 0;
            $insights[]     = $repeatCount . ' of ' . $totalCustCount . ' customers (' . $repeatPct .
                '%) have booked 2 or more times in this period';
        }

        return [
            'kpis' => [
                'top_customer_name'     => $topCustomer['name'],
                'top_customer_revenue'  => round($topCustomer['revenue'], 2),
                'top_customer_bookings' => $topCustomer['bookings'],
                'top_customer_tourists' => $topCustomer['tourists'],
                'top3_revenue'          => round($top3Revenue, 2),
                'top3_names'            => $top3Names,
                'top3_pct'              => $top3Pct,
                'repeat_customers'      => $repeatCount,
                'total_customers'       => count($customersArr),
                'avg_rev_per_tourist'   => $avgRevPerTourist,
                'rev_per_tourist_min'   => round($rptMin, 2),
                'rev_per_tourist_max'   => round($rptMax, 2),
            ],
            'chart_customers' => [
                'labels'  => $chartLabels,
                'revenue' => $chartRevenue,
            ],
            'chart_group_avg' => [
                'labels' => array_keys($segmentRanges),
                'avg'    => $groupAvgValues,
            ],
            'table_customers' => $tableCustomers,
            'insights'        => $insights,
        ];
    }

    // ── Insights Tab ───────────────────────────────────────────────────

    public static function getInsightsData($fromDate, $toDate, $tourPackageIds = [], $paymentTypes = [], $thirdPartyPlatforms = [], $requestFromValues = [], $bookingSources = [])
    {
        $rows     = self::getDashboardRows($fromDate, $toDate, $tourPackageIds, $paymentTypes, $thirdPartyPlatforms, $requestFromValues, $bookingSources);
        $bookings = self::aggregateBookings($rows);

        if (empty($bookings)) {
            return ['sections' => []];
        }

        $customerByBooking      = [];
        $bookingWindowByBooking = [];

        foreach ($rows as $row) {
            $id = $row->tour_booking_id;
            if (!isset($customerByBooking[$id])) {
                $customerByBooking[$id] = [
                    'id'   => $row->customer_id,
                    'name' => $row->customer_name,
                ];
                $bookingWindowByBooking[$id] = max(0, (int) ((strtotime($row->date) - strtotime(substr($row->created_at, 0, 10))) / 86400));
            }
        }

        $result  = self::computeInsights($bookings, $customerByBooking, $bookingWindowByBooking);
        $geoData = self::buildInsightsGeoSection($rows, $bookings);
        if ($geoData) {
            if (!empty($geoData['section'])) {
                $result['sections'][] = $geoData['section'];
            }
            if (!empty($geoData['key_patterns'])) {
                $nextNum = count($result['key_patterns'] ?? []) + 1;
                foreach ($geoData['key_patterns'] as $kp) {
                    $kp['num']            = $nextNum++;
                    $result['key_patterns'][] = $kp;
                }
            }
        }
        return $result;
    }

    public static function computeInsights(array $bookings, array $customerByBooking, array $bookingWindowByBooking): array
    {
        $sections = [];

        // ── Daily / weekly / monthly / day-of-week aggregates ─────────
        $dailyRevenue  = [];
        $weeklyRevenue = [];
        $monthlyData   = [];
        $dowData       = [];
        for ($d = 0; $d <= 6; $d++) {
            $dowData[$d] = ['net' => 0.0, 'bookings' => 0];
        }

        $segmentRanges = [
            'Solo(1)'     => [1, 1],
            'Pair(2)'     => [2, 2],
            'Small(3-4)'  => [3, 4],
            'Medium(5-6)' => [5, 6],
            'Group(7-10)' => [7, 10],
            'Large(10+)'  => [11, PHP_INT_MAX],
        ];
        $groupData = array_fill_keys(array_keys($segmentRanges), ['bookings' => 0, 'revenue' => 0.0, 'tourists' => 0]);

        $customers    = [];
        $totalRevenue = 0.0;
        $allWindows   = [];

        $windowBuckets = [
            '0–7 days'    => ['count' => 0, 'tourists' => 0],
            '8–30 days'   => ['count' => 0, 'tourists' => 0],
            '31–90 days'  => ['count' => 0, 'tourists' => 0],
            '91–180 days' => ['count' => 0, 'tourists' => 0],
            '180+ days'   => ['count' => 0, 'tourists' => 0],
        ];

        foreach ($bookings as $bookingId => $b) {
            if ($b['net'] <= 0) continue;

            $ts    = strtotime($b['date']);
            $ds    = $b['date'];
            $week  = date('o-W', $ts);
            $month = substr($b['date'], 0, 7);
            $dow   = (int) date('w', $ts);

            $dailyRevenue[$ds]    = ($dailyRevenue[$ds]  ?? 0.0) + $b['net'];
            $weeklyRevenue[$week] = ($weeklyRevenue[$week] ?? 0.0) + $b['net'];

            if (!isset($monthlyData[$month])) {
                $monthlyData[$month] = ['net' => 0.0, 'tourists' => 0, 'bookings' => 0];
            }
            $monthlyData[$month]['net']      += $b['net'];
            $monthlyData[$month]['tourists'] += $b['tourists'];
            $monthlyData[$month]['bookings']++;

            $dowData[$dow]['net']      += $b['net'];
            $dowData[$dow]['bookings']++;

            // Group size
            $pax = $b['total_pax'];
            foreach ($segmentRanges as $label => [$min, $max]) {
                if ($pax >= $min && $pax <= $max) {
                    $groupData[$label]['bookings']++;
                    $groupData[$label]['revenue']  += $b['net'];
                    $groupData[$label]['tourists'] += $b['tourists'];
                    break;
                }
            }

            // Customers
            $cInfo = $customerByBooking[$bookingId] ?? ['id' => 0, 'name' => 'Unknown'];
            $cId   = $cInfo['id'];
            if (!isset($customers[$cId])) {
                $customers[$cId] = ['name' => $cInfo['name'], 'bookings' => 0, 'revenue' => 0.0, 'tourists' => 0];
            }
            $customers[$cId]['bookings']++;
            $customers[$cId]['revenue']  += $b['net'];
            $customers[$cId]['tourists'] += $b['tourists'];
            $totalRevenue += $b['net'];

            // Booking window
            $days = $bookingWindowByBooking[$bookingId] ?? 0;
            if ($days <= 7)       $bucket = '0–7 days';
            elseif ($days <= 30)  $bucket = '8–30 days';
            elseif ($days <= 90)  $bucket = '31–90 days';
            elseif ($days <= 180) $bucket = '91–180 days';
            else                  $bucket = '180+ days';
            $windowBuckets[$bucket]['count']++;
            $windowBuckets[$bucket]['tourists'] += $b['tourists'];
            $allWindows[] = $days;
        }

        ksort($weeklyRevenue);
        ksort($monthlyData);
        sort($allWindows);

        // Pre-compute values reused across multiple sections
        $totalWindowCount = array_sum(array_column($windowBuckets, 'count'));
        $mostCommonBucket = '';
        $mostCommonCount  = 0;
        foreach ($windowBuckets as $bucket => $d) {
            if ($d['count'] > $mostCommonCount) {
                $mostCommonCount  = $d['count'];
                $mostCommonBucket = $bucket;
            }
        }

        // Single DOW pass — used in section 3 card and key pattern 7
        $dowNames   = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $bestDow    = -1; $bestAvg    = 0;
        $worstDow   = -1; $worstAvg   = PHP_INT_MAX;
        $highVolDow = -1; $highVolAmt = 0;
        foreach ($dowData as $d => $data) {
            if ($data['bookings'] === 0) continue;
            $avg = $data['net'] / $data['bookings'];
            if ($avg > $bestAvg)            { $bestAvg    = $avg;          $bestDow    = $d; }
            if ($avg < $worstAvg)           { $worstAvg   = $avg;          $worstDow   = $d; }
            if ($data['net'] > $highVolAmt) { $highVolAmt = $data['net'];  $highVolDow = $d; }
        }
        if ($worstAvg === PHP_INT_MAX) $worstAvg = 0;

        // ─────────────────────────────────────────────────────────────
        // SECTION 1: REVENUE PERFORMANCE
        // ─────────────────────────────────────────────────────────────
        $revCards = [];

        // Card 1: Weekly revenue trend
        $weekKeys = array_keys($weeklyRevenue);
        if (count($weekKeys) >= 2) {
            $firstWeekRev = $weeklyRevenue[$weekKeys[0]];
            $lastWeekRev  = $weeklyRevenue[$weekKeys[count($weekKeys) - 1]];
            $totalWeeks   = count($weekKeys);
            if ($firstWeekRev > 0 && $lastWeekRev > 0) {
                $multiple = round($lastWeekRev / $firstWeekRev, 0);
                $verb     = $multiple >= 1 ? 'Grew' : 'Declined';
                $revCards[] = [
                    'title'  => 'Weekly Revenue ' . $verb . ' ' . $multiple . '× Over ' . $totalWeeks . ' Weeks',
                    'body'   => 'From ' . self::formatCurrencyShort($firstWeekRev) . ' in the first week to '
                              . self::formatCurrencyShort($lastWeekRev) . ' in the most recent — '
                              . 'sustained across ' . $totalWeeks . ' consecutive weeks.',
                    'accent' => 'green',
                ];
            }
        } elseif (count($weekKeys) === 1) {
            $revCards[] = [
                'title'  => 'Revenue This Week',
                'body'   => 'Net revenue of ' . self::formatCurrencyShort(current($weeklyRevenue)) . ' recorded this week.',
                'accent' => 'green',
            ];
        }

        // Card 2: High-revenue days
        if (!empty($dailyRevenue)) {
            arsort($dailyRevenue);
            $topDay    = key($dailyRevenue);
            $topDayRev = current($dailyRevenue);
            $allRevs   = array_values($dailyRevenue);
            // Dynamic threshold: highest round-number below 50% of peak
            $threshold = max(1000, (int) ($topDayRev * 0.5 / 1000) * 1000);
            $aboveCount = count(array_filter($allRevs, function ($v) use ($threshold) { return $v >= $threshold; }));
            $topDayLabel = Carbon::parse($topDay)->format('M j');
            $revCards[] = [
                'title'  => $aboveCount . ' Day' . ($aboveCount !== 1 ? 's' : '') . ' Above ' . self::formatCurrencyShort($threshold),
                'body'   => $topDayLabel . ' set the single-day record at ' . self::formatCurrencyShort($topDayRev) . '. '
                          . ($aboveCount > 1
                              ? ($aboveCount . ' days exceeded ' . self::formatCurrencyShort($threshold) . ' across the period.')
                              : 'This was the only day to reach this level.'),
                'accent' => 'green',
            ];
        }

        // Card 3: Peak month
        if (count($monthlyData) >= 2) {
            $peakMonth = '';
            $peakNet   = 0;
            foreach ($monthlyData as $m => $d) {
                if ($d['net'] > $peakNet) { $peakNet = $d['net']; $peakMonth = $m; }
            }
            $daysInPeak = 0;
            foreach ($dailyRevenue as $ds => $_) {
                if (substr($ds, 0, 7) === $peakMonth) $daysInPeak++;
            }
            $peakLabel    = Carbon::parse($peakMonth . '-01')->format('F Y');
            $peakDailyAvg = $daysInPeak > 0 ? $peakNet / $daysInPeak : 0;
            $revCards[] = [
                'title'  => $peakLabel . ' Was the Peak Month',
                'body'   => self::formatCurrencyShort($peakNet) . ' collected across ' . $daysInPeak . ' active days'
                          . ($peakDailyAvg > 0 ? ' — ' . self::formatCurrencyShort($peakDailyAvg) . '/day average.' : '.'),
                'accent' => 'green',
            ];
        }

        if (!empty($revCards)) {
            $sections[] = ['label' => 'REVENUE PERFORMANCE', 'cards' => $revCards];
        }

        // ─────────────────────────────────────────────────────────────
        // SECTION 2: PRICING & YIELD
        // ─────────────────────────────────────────────────────────────
        $pricingCards = [];

        // Card 1: Rev/tourist consistency across group sizes
        $rptValues = [];
        foreach ($groupData as $label => $d) {
            if ($d['tourists'] > 0 && $d['revenue'] > 0) {
                $rptValues[$label] = $d['revenue'] / $d['tourists'];
            }
        }
        if (count($rptValues) >= 2) {
            $rptMin   = min($rptValues);
            $rptMax   = max($rptValues);
            $spread   = $rptMax > 0 ? ($rptMax - $rptMin) / $rptMax : 0;
            $label    = $spread < 0.25 ? 'Flat' : ($spread < 0.5 ? 'Broadly Consistent' : 'Varied');
            $minLabel = str_replace(['(', ')'], ['', ''], array_search($rptMin, $rptValues));
            $maxLabel = str_replace(['(', ')'], ['', ''], array_search($rptMax, $rptValues));
            $pricingCards[] = [
                'title'  => 'Revenue Per Tourist Is ' . $label . ' Across All Group Sizes',
                'body'   => $minLabel . ' visitors yield ' . self::formatCurrencyShort($rptMin) . '/head. '
                          . $maxLabel . ' groups yield ' . self::formatCurrencyShort($rptMax) . '/head. '
                          . 'The spread across all ' . count($rptValues) . ' size segments is only '
                          . self::formatCurrencyShort($rptMax - $rptMin) . '.',
                'accent' => 'teal',
            ];
        }

        // Card 2: Advance booker analysis
        if ($totalWindowCount > 0) {
            $sameDayCount  = $windowBuckets['0–7 days']['count'];
            $sameDayAvgPax = $sameDayCount > 0 ? $windowBuckets['0–7 days']['tourists'] / $sameDayCount : 0;
            $farAdvCount   = $windowBuckets['180+ days']['count'];
            $farAdvAvgPax  = $farAdvCount > 0 ? $windowBuckets['180+ days']['tourists'] / $farAdvCount : 0;

            if ($sameDayCount > 0 && $farAdvCount > 0 && $farAdvAvgPax > $sameDayAvgPax) {
                $multiple = round($farAdvAvgPax / $sameDayAvgPax, 1);
                $pricingCards[] = [
                    'title'  => 'Advance Bookers Bring More Tourists Per Booking',
                    'body'   => 'Same-day bookers average ' . round($sameDayAvgPax, 1) . ' pax per booking. '
                              . 'Those who book 180+ days ahead average ' . round($farAdvAvgPax, 1) . ' pax — '
                              . $multiple . '× more tourists per transaction.',
                    'accent' => 'teal',
                ];
            } else {
                $mostCommonPct = round($mostCommonCount / $totalWindowCount * 100, 1);
                $pricingCards[] = [
                    'title'  => $mostCommonBucket . ' Is the Most Common Booking Window',
                    'body'   => number_format($mostCommonCount) . ' bookings (' . $mostCommonPct . '%) were placed '
                              . $mostCommonBucket . ' before the tour.',
                    'accent' => 'teal',
                ];
            }
        }

        // Card 3: Month-over-month rev/tourist trend
        $monthKeys = array_keys($monthlyData);
        if (count($monthKeys) >= 2) {
            $firstMonth = $monthKeys[0];
            $lastMonth  = $monthKeys[count($monthKeys) - 1];
            $firstRPT   = $monthlyData[$firstMonth]['tourists'] > 0
                ? $monthlyData[$firstMonth]['net'] / $monthlyData[$firstMonth]['tourists'] : 0;
            $lastRPT    = $monthlyData[$lastMonth]['tourists'] > 0
                ? $monthlyData[$lastMonth]['net'] / $monthlyData[$lastMonth]['tourists'] : 0;
            if ($firstRPT > 0 && $lastRPT > 0) {
                $trend      = $lastRPT >= $firstRPT ? 'Rose' : 'Declined Slightly';
                $firstLabel = Carbon::parse($firstMonth . '-01')->format('M Y');
                $lastLabel  = Carbon::parse($lastMonth  . '-01')->format('M Y');
                $pricingCards[] = [
                    'title'  => 'Revenue Per Tourist ' . $trend . ' Month-on-Month',
                    'body'   => $firstLabel . ': ' . self::formatCurrencyShort($firstRPT) . '/tourist → '
                              . $lastLabel  . ': ' . self::formatCurrencyShort($lastRPT) . '/tourist. '
                              . ($lastRPT < $firstRPT
                                  ? 'As volume scaled, the blended yield compressed slightly — reflecting a higher share of standard-rate bookings as the period progressed.'
                                  : 'Yield strengthened as the period progressed.'),
                    'accent' => 'teal',
                ];
            }
        }

        if (!empty($pricingCards)) {
            $sections[] = ['label' => 'PRICING & YIELD', 'cards' => $pricingCards];
        }

        // ─────────────────────────────────────────────────────────────
        // SECTION 3: CUSTOMER & BOOKING BEHAVIOUR
        // ─────────────────────────────────────────────────────────────
        $custCards = [];

        // Card 1: Top customers revenue concentration
        if (!empty($customers) && $totalRevenue > 0) {
            uasort($customers, function ($a, $b) { return $b['revenue'] <=> $a['revenue']; });
            $customersArr = array_values($customers);
            $topN         = min(10, count($customersArr));
            $topSlice     = array_slice($customersArr, 0, $topN);
            $topRevenue   = array_sum(array_column($topSlice, 'revenue'));
            $topPct       = round($topRevenue / $totalRevenue * 100, 1);

            $names = [];
            foreach (array_slice($topSlice, 0, 3) as $c) {
                $names[] = $c['name'] . ' (' . self::formatCurrencyShort($c['revenue']) . ')';
            }
            $custCards[] = [
                'title'  => 'Top ' . $topN . ' Customers Generated ' . self::formatCurrencyShort($topRevenue) . ' — ' . $topPct . '% of Revenue',
                'body'   => implode(', ', $names) . ' together account for a significant share of total revenue.',
                'accent' => 'green',
            ];
        }

        // Card 2: Most common booking window
        if ($totalWindowCount > 0) {
            $mostCommonPct = round($mostCommonCount / $totalWindowCount * 100, 1);
            $medianWindow  = count($allWindows) > 0 ? $allWindows[(int) floor(count($allWindows) / 2)] : 0;
            $custCards[] = [
                'title'  => $mostCommonBucket . ' Is the Most Common Booking Window',
                'body'   => number_format($mostCommonCount) . ' bookings (' . $mostCommonPct . '%) were placed '
                          . $mostCommonBucket . ' before the tour. The median is '
                          . $medianWindow . ' days.',
                'accent' => 'green',
            ];
        }

        // Card 3: Day of week — uses precomputed DOW vars
        if ($bestDow >= 0) {
            $body = $dowNames[$bestDow] . ' averages ' . self::formatCurrencyShort($bestAvg) . ' per booking — the highest of any weekday.';
            if ($highVolDow >= 0 && $highVolDow !== $bestDow) {
                $body .= ' ' . $dowNames[$highVolDow] . ' has the highest total revenue (' . self::formatCurrencyShort($highVolAmt) . ') due to volume.';
            }
            if ($worstDow >= 0) {
                $body .= ' ' . $dowNames[$worstDow] . ' has the lowest avg at ' . self::formatCurrencyShort($worstAvg) . ' per booking.';
            }
            $custCards[] = [
                'title'  => $dowNames[$bestDow] . ' Has the Highest Revenue Per Booking',
                'body'   => $body,
                'accent' => 'orange',
            ];
        }

        if (!empty($custCards)) {
            $sections[] = ['label' => 'CUSTOMER & BOOKING BEHAVIOUR', 'cards' => $custCards];
        }

        // ─────────────────────────────────────────────────────────────
        // KEY PATTERNS SUMMARY TABLE
        // ─────────────────────────────────────────────────────────────
        $totalBookings = count(array_filter($bookings, fn($b) => $b['net'] > 0));
        $keyPatterns   = [];

        // Patterns 1–3: single pass over group segments
        $maxRevLabel    = ''; $maxRevAmount   = 0; $maxRevBookings = 0;
        $maxBookLabel   = ''; $maxBookCount   = 0; $maxBookRevenue = 0;
        $maxRPBLabel    = ''; $maxRPB         = 0; $maxRPBSeg      = null;

        foreach ($groupData as $lbl => $d) {
            if ($d['revenue'] > $maxRevAmount) {
                $maxRevAmount = $d['revenue']; $maxRevLabel = $lbl; $maxRevBookings = $d['bookings'];
            }
            if ($d['bookings'] > $maxBookCount) {
                $maxBookCount = $d['bookings']; $maxBookLabel = $lbl; $maxBookRevenue = $d['revenue'];
            }
            if ($d['bookings'] > 0) {
                $rpb = $d['revenue'] / $d['bookings'];
                if ($rpb > $maxRPB) { $maxRPB = $rpb; $maxRPBLabel = $lbl; $maxRPBSeg = $d; }
            }
        }

        // 1. Group segment with the most revenue
        if ($maxRevLabel && $totalRevenue > 0) {
            $maxRevPct   = round($maxRevAmount / $totalRevenue * 100, 1);
            $keyPatterns[] = [
                'num'        => 1,
                'pattern'    => $maxRevLabel . ' groups drive the most revenue',
                'data_point' => number_format($maxRevBookings) . ' bookings · ' . self::formatCurrencyShort($maxRevAmount) . ' — largest revenue segment',
                'scale'      => $maxRevPct . '% of total revenue',
                'period'     => 'Full period',
            ];
        }

        // 2. Group segment with the most bookings
        if ($maxBookLabel && $totalBookings > 0) {
            $maxBookPct    = round($maxBookCount   / $totalBookings  * 100, 1);
            $maxBookRevPct = $totalRevenue > 0 ? round($maxBookRevenue / $totalRevenue * 100, 1) : 0;
            $keyPatterns[] = [
                'num'        => 2,
                'pattern'    => $maxBookLabel . ' are the most frequent booking type',
                'data_point' => number_format($maxBookCount) . ' bookings — ' . $maxBookPct . '% of all transactions',
                'scale'      => self::formatCurrencyShort($maxBookRevenue) . ' · ' . $maxBookRevPct . '% of revenue',
                'period'     => 'Full period',
            ];
        }

        // 3. Group segment with highest rev per booking
        if ($maxRPBLabel && $maxRPBSeg && $totalBookings > 0) {
            $maxRPBPct     = round($maxRPBSeg['bookings'] / $totalBookings * 100, 1);
            $avgPaxPerBook = $maxRPBSeg['bookings'] > 0 ? round($maxRPBSeg['tourists'] / $maxRPBSeg['bookings'], 0) : 0;
            $keyPatterns[] = [
                'num'        => 3,
                'pattern'    => $maxRPBLabel . ' yield the highest rev per booking',
                'data_point' => number_format($maxRPBSeg['bookings']) . ' bookings · avg ' . self::formatCurrencyShort($maxRPB) . ' each · avg ' . $avgPaxPerBook . ' pax/booking',
                'scale'      => self::formatCurrencyShort($maxRPBSeg['revenue']) . ' from ' . $maxRPBPct . '% of bookings',
                'period'     => 'Full period',
            ];
        }

        // 4. Revenue per tourist stability across group sizes
        if (count($rptValues) >= 2) {
            $rptMinLbl  = str_replace(['(', ')'], '', array_search($rptMin, $rptValues));
            $rptMaxLbl  = str_replace(['(', ')'], '', array_search($rptMax, $rptValues));
            $keyPatterns[] = [
                'num'        => 4,
                'pattern'    => 'Revenue per tourist is stable across all group sizes',
                'data_point' => 'Range: ' . self::formatCurrencyShort($rptMin) . ' (' . $rptMinLbl . ') to ' . self::formatCurrencyShort($rptMax) . ' (' . $rptMaxLbl . ')',
                'scale'      => self::formatCurrencyShort($rptMax - $rptMin) . ' spread across ' . count($rptValues) . ' segments',
                'period'     => 'Full period',
            ];
        }

        // 5. Steepest month-over-month revenue growth
        $steepestMonth    = '';
        $steepestGrowthPct = 0;
        $steepestNet      = 0;
        $steepestPrevNet  = 0;
        $steepestBookings = 0;
        $prevMNet         = 0;
        $prevMKey         = '';
        foreach ($monthlyData as $m => $d) {
            if ($prevMNet > 0 && $d['net'] > 0) {
                $growthPct = ($d['net'] - $prevMNet) / $prevMNet * 100;
                if ($growthPct > $steepestGrowthPct) {
                    $steepestGrowthPct = $growthPct;
                    $steepestMonth     = $m;
                    $steepestNet       = $d['net'];
                    $steepestPrevNet   = $prevMNet;
                    $steepestBookings  = $d['bookings'];
                }
            }
            $prevMNet = $d['net'];
            $prevMKey = $m;
        }
        if ($steepestMonth) {
            $steepestLabel     = Carbon::parse($steepestMonth . '-01')->format('M Y');
            $steepestPrevLabel = Carbon::parse($steepestMonth . '-01')->subMonth()->format('M Y');
            $daysInMonth = Carbon::parse($steepestMonth . '-01')->daysInMonth;
            $keyPatterns[] = [
                'num'        => 5,
                'pattern'    => $steepestLabel . ' was the steepest growth month',
                'data_point' => self::formatCurrencyShort($steepestNet) . ' — a ' . round($steepestGrowthPct, 0) . '% jump from ' . $steepestPrevLabel . '\'s ' . self::formatCurrencyShort($steepestPrevNet),
                'scale'      => number_format($steepestBookings) . ' bookings in ' . $daysInMonth . ' days',
                'period'     => $steepestLabel,
            ];
        }

        // 6. Advance bookers vs same-day group size
        $sameDayCnt  = $windowBuckets['0–7 days']['count'];
        $advCnt      = $windowBuckets['180+ days']['count'];
        $sameDayAvgP = $sameDayCnt > 0 ? round($windowBuckets['0–7 days']['tourists'] / $sameDayCnt, 1) : 0;
        $advAvgP     = $advCnt  > 0 ? round($windowBuckets['180+ days']['tourists'] / $advCnt, 1) : 0;
        if ($sameDayCnt > 0 && $advCnt > 0) {
            $totalAdvCount = $windowBuckets['91–180 days']['count'] + $windowBuckets['180+ days']['count'];
            $keyPatterns[] = [
                'num'        => 6,
                'pattern'    => 'Advance bookers bring bigger groups',
                'data_point' => '180+ day bookers avg ' . $advAvgP . ' pax vs ' . $sameDayAvgP . ' pax for same-day',
                'scale'      => number_format($totalAdvCount) . ' advance bookings',
                'period'     => 'Full period',
            ];
        }

        // 7. Day-of-week: highest total revenue vs highest per booking (uses precomputed DOW vars)
        if ($highVolDow >= 0 && $bestDow >= 0) {
            $keyPatterns[] = [
                'num'        => 7,
                'pattern'    => $dowNames[$highVolDow] . ' highest total revenue, ' . $dowNames[$bestDow] . ' highest per booking',
                'data_point' => $dowNames[$highVolDow] . ' ' . self::formatCurrencyShort($highVolAmt) . ' total · ' . $dowNames[$bestDow] . ' ' . self::formatCurrencyShort($bestAvg) . ' avg per booking',
                'scale'      => '7-day analysis',
                'period'     => 'Full period',
            ];
        }

        // 8. Weekly revenue growth: first week to peak week
        if (count($weeklyRevenue) >= 2) {
            $weekKeys     = array_keys($weeklyRevenue);
            $firstWkKey   = $weekKeys[0];
            $firstWkRev   = $weeklyRevenue[$firstWkKey];
            $peakWkRev    = max($weeklyRevenue);
            $peakWkKey    = array_search($peakWkRev, $weeklyRevenue);
            $lastWkKey    = $weekKeys[count($weekKeys) - 1];
            $totalWeeksCnt = count($weeklyRevenue);
            if ($firstWkRev > 0) {
                $multiple = round($peakWkRev / $firstWkRev, 0);
                // Build period label e.g. "Oct 25–Mar 26"
                [$firstYear, $firstWeekNum] = explode('-W', str_replace('-', '-W', $firstWkKey));
                [$lastYear,  $lastWeekNum]  = explode('-W', str_replace('-', '-W', $lastWkKey));
                $firstWkLabel = Carbon::now()->setISODate((int)$firstYear, (int)$firstWeekNum)->format('M y');
                $lastWkLabel  = Carbon::now()->setISODate((int)$lastYear,  (int)$lastWeekNum)->format('M y');
                [$peakYear, $peakWeekNum] = explode('-W', str_replace('-', '-W', $peakWkKey));
                $peakWkLabel  = 'W' . $peakWeekNum . ' ' . Carbon::now()->setISODate((int)$peakYear, (int)$peakWeekNum)->format('M y');
                $keyPatterns[] = [
                    'num'        => 8,
                    'pattern'    => 'Weekly revenue grew ' . $multiple . '× from first to peak week',
                    'data_point' => self::formatCurrencyShort($firstWkRev) . ' (Wk 1) to ' . self::formatCurrencyShort($peakWkRev) . ' (' . $peakWkLabel . ')',
                    'scale'      => $totalWeeksCnt . '-week sustained growth',
                    'period'     => $firstWkLabel . '–' . $lastWkLabel,
                ];
            }
        }

        return ['sections' => $sections, 'key_patterns' => $keyPatterns];
    }

    private static function buildInsightsGeoSection(iterable $rows, array $bookings = []): ?array
    {
        // Build per-booking metadata (country, date, created_at) from raw rows.
        $bookingMeta = [];
        foreach ($rows as $row) {
            $id = $row->tour_booking_id;
            if (!isset($bookingMeta[$id])) {
                $bookingMeta[$id] = [
                    'country'    => $row->country    ?? '',
                    'date'       => $row->date       ?? '',
                    'created_at' => $row->created_at ?? '',
                ];
            }
        }

        $countryData = [];

        foreach ($bookings as $id => $b) {
            if (!($b['has_payment'] ?? false)) continue;

            $meta    = $bookingMeta[$id] ?? [];
            $country = (!empty($meta['country']) && strtolower(trim($meta['country'])) !== 'unknown')
                ? trim($meta['country'])
                : null;
            if (!$country) continue;

            $leadDays = max(0, (int) ((strtotime($meta['date'] ?? '') - strtotime(substr($meta['created_at'] ?? '', 0, 10))) / 86400));

            if (!isset($countryData[$country])) {
                $countryData[$country] = ['country' => $country, 'bookings' => 0, 'tourists' => 0, 'lead_days_sum' => 0];
            }
            $countryData[$country]['bookings']++;
            // $b['tourists'] is corrected by aggregateBookings() — unpaid pax already subtracted.
            $countryData[$country]['tourists']      += $b['tourists'];
            $countryData[$country]['lead_days_sum'] += $leadDays;
        }

        if (empty($countryData)) return null;

        foreach ($countryData as &$cd) {
            $cd['avg_lead_days'] = $cd['bookings'] > 0 ? (int) round($cd['lead_days_sum'] / $cd['bookings']) : 0;
            unset($cd['lead_days_sum']);
        }
        unset($cd);

        $qualified  = array_values(array_filter($countryData, fn($c) => $c['bookings'] >= 3));
        $totalKnown = array_sum(array_column($countryData, 'bookings'));

        if (empty($qualified)) return null;

        // ── US data + international in a single pass ──────────────
        $usBookings  = 0; $usTourists = 0; $usAvgLead = 0;
        $intlLeadSum = 0; $intlLeadCnt = 0;
        $usAliasSet  = array_flip(self::US_ALIASES);

        foreach ($countryData as $cd) {
            if (isset($usAliasSet[$cd['country']])) {
                $usBookings += $cd['bookings'];
                $usTourists += $cd['tourists'];
                $usAvgLead   = $cd['avg_lead_days'];
            } else {
                $intlLeadSum += $cd['avg_lead_days'] * $cd['bookings'];
                $intlLeadCnt += $cd['bookings'];
            }
        }
        $intlCnt     = $totalKnown - $usBookings;
        $intlAvgLead = $intlLeadCnt > 0 ? (int) round($intlLeadSum / $intlLeadCnt) : 0;

        // ── Cards ─────────────────────────────────────────────────
        $cards = [];

        usort($qualified, fn($a, $b) => $b['avg_lead_days'] <=> $a['avg_lead_days']);
        $topEarly = array_slice($qualified, 0, 5);
        if (!empty($topEarly) && $topEarly[0]['avg_lead_days'] > 30) {
            $lead    = $topEarly[0];
            $others  = array_slice($topEarly, 1, 4);
            $details = implode(', ', array_map(fn($c) => $c['country'] . ' (' . $c['avg_lead_days'] . ' days)', $others));
            $cards[] = [
                'title'  => $lead['country'] . ' Visitors Book ' . $lead['avg_lead_days'] . ' Days Ahead on Average',
                'body'   => (!empty($details) ? $details . ' also plan well ahead. ' : '')
                          . 'These countries book significantly further in advance than the overall average.',
                'accent' => 'teal',
            ];
        }

        $lmCandidates = array_values(array_filter($qualified, fn($c) => $c['avg_lead_days'] > 0));
        usort($lmCandidates, fn($a, $b) => $a['avg_lead_days'] <=> $b['avg_lead_days']);
        $topLate = array_slice($lmCandidates, 0, 4);
        if (count($topLate) >= 2) {
            $parts = array_map(fn($c) => $c['country'] . ' (' . $c['avg_lead_days'] . ' days)', $topLate);
            $last  = array_pop($parts);
            $cards[] = [
                'title'  => $topLate[0]['country'] . ' Are Last-Minute Bookers at ' . $topLate[0]['avg_lead_days'] . ' Days Avg',
                'body'   => implode(', ', $parts) . ' and ' . $last
                          . '. These visitors decide and purchase quickly compared to other markets.',
                'accent' => 'orange',
            ];
        }

        if ($usBookings > 0 && $totalKnown > 0) {
            $usPct   = round($usBookings / $totalKnown * 100, 1);
            $intlPct = round(100 - $usPct, 1);
            $cards[] = [
                'title'  => 'US Customers Account for ' . $usPct . '% of Tracked Bookings',
                'body'   => number_format($usBookings) . ' US bookings (' . number_format($usTourists) . ' tourists). '
                          . 'International customers account for ' . $intlPct . '% — '
                          . number_format($intlCnt) . ' bookings from other countries.',
                'accent' => 'green',
            ];
        }

        // ── Key Patterns ──────────────────────────────────────────
        $keyPatterns = [];

        // Pattern: Top early-planning countries (European focus)
        $topEarlyKp = array_slice($qualified, 0, 4);
        if (!empty($topEarlyKp) && $topEarlyKp[0]['avg_lead_days'] > 30) {
            $europeanQualified = array_filter($topEarlyKp, fn($c) => in_array($c['country'], self::EUROPEAN_COUNTRIES));
            $isEuropeanDominated = count($europeanQualified) >= 2;
            $label   = $isEuropeanDominated ? 'European customers book furthest ahead' : 'Top countries plan furthest ahead';
            $dotParts = array_map(fn($c) => $c['country'] . ' ' . $c['avg_lead_days'] . 'd', $topEarlyKp);
            $scaleBookings = array_sum(array_column(
                array_filter($countryData, fn($c) => in_array($c['country'], array_column($topEarlyKp, 'country'))),
                'bookings'
            ));
            $scaleLabel = $isEuropeanDominated ? number_format($scaleBookings) . ' European bookings' : number_format($scaleBookings) . ' bookings';
            $keyPatterns[] = [
                'pattern'    => $label,
                'data_point' => implode(' · ', $dotParts),
                'scale'      => $scaleLabel,
                'period'     => 'Full period',
            ];
        }

        // Pattern: International vs US lead time
        if ($usBookings > 0 && $intlAvgLead > 0 && $usAvgLead > 0) {
            $pctDiff = $usAvgLead > 0 ? (int) round(abs($intlAvgLead - $usAvgLead) / $usAvgLead * 100) : 0;
            $direction = $intlAvgLead > $usAvgLead ? 'earlier' : 'later';
            $keyPatterns[] = [
                'pattern'    => 'International customers book ' . $pctDiff . '% ' . $direction . ' than US',
                'data_point' => 'Intl avg ' . $intlAvgLead . ' days vs US avg ' . $usAvgLead . ' days lead time',
                'scale'      => number_format($intlCnt) . ' international bookings',
                'period'     => 'Full period',
            ];
        }

        if (empty($cards) && empty($keyPatterns)) return null;

        return [
            'section'      => !empty($cards) ? ['label' => 'GEOGRAPHIC PATTERNS', 'cards' => $cards] : null,
            'key_patterns' => $keyPatterns,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // GEOGRAPHIC TAB
    // ─────────────────────────────────────────────────────────────
    public static function getGeographicData($fromDate, $toDate, $tourPackageIds = [], $paymentTypes = [], $thirdPartyPlatforms = [], $requestFromValues = [], $bookingSources = [])
    {
        $rows     = self::getDashboardRows($fromDate, $toDate, $tourPackageIds, $paymentTypes, $thirdPartyPlatforms, $requestFromValues, $bookingSources);
        $bookings = self::aggregateBookings($rows);

        // Build per-booking metadata (country, date, created_at) from raw rows.
        // Only the first row per booking is needed; aggregateBookings() deduplicates.
        $bookingMeta = [];
        foreach ($rows as $row) {
            $id = $row->tour_booking_id;
            if (!isset($bookingMeta[$id])) {
                $bookingMeta[$id] = [
                    'country'    => $row->country    ?? '',
                    'date'       => $row->date       ?? '',
                    'created_at' => $row->created_at ?? '',
                ];
            }
        }

        $countryData  = [];
        $leadBuckets  = [
            'Same-day'    => 0,
            '1–7 days'    => 0,
            '8–30 days'   => 0,
            '31–90 days'  => 0,
            '91–180 days' => 0,
            '180+ days'   => 0,
        ];
        $totalBookings = 0;

        foreach ($bookings as $id => $b) {
            // Skip abandoned (Initialized-only) bookings — same guard as other KPIs.
            if (!($b['has_payment'] ?? false)) continue;

            $meta    = $bookingMeta[$id] ?? [];
            $country = (!empty($meta['country']) && strtolower(trim($meta['country'])) !== 'unknown')
                ? trim($meta['country'])
                : 'Unknown';

            $leadDays = max(0, (int) ((strtotime($meta['date'] ?? '') - strtotime(substr($meta['created_at'] ?? '', 0, 10))) / 86400));

            if (!isset($countryData[$country])) {
                $countryData[$country] = [
                    'country'       => $country,
                    'bookings'      => 0,
                    'tourists'      => 0,
                    'revenue'       => 0.0,
                    'lead_days_sum' => 0,
                ];
            }

            $countryData[$country]['bookings']++;
            // $b['tourists'] is already adjusted by aggregateBookings() — unpaid pax subtracted.
            $countryData[$country]['tourists']      += $b['tourists'];
            $countryData[$country]['revenue']       += $b['net'];
            $countryData[$country]['lead_days_sum'] += $leadDays;

            if      ($leadDays === 0)  $leadBuckets['Same-day']++;
            elseif  ($leadDays <= 7)   $leadBuckets['1–7 days']++;
            elseif  ($leadDays <= 30)  $leadBuckets['8–30 days']++;
            elseif  ($leadDays <= 90)  $leadBuckets['31–90 days']++;
            elseif  ($leadDays <= 180) $leadBuckets['91–180 days']++;
            else                       $leadBuckets['180+ days']++;

            $totalBookings++;
        }

        foreach ($countryData as &$cd) {
            $avg = $cd['bookings'] > 0 ? round($cd['lead_days_sum'] / $cd['bookings']) : 0;
            $cd['avg_lead_days'] = $avg;
            unset($cd['lead_days_sum']);

            if      ($avg === 0) $cd['booking_style'] = 'Same-day';
            elseif  ($avg <= 7)  $cd['booking_style'] = 'Last minute';
            elseif  ($avg <= 30) $cd['booking_style'] = 'Late planner';
            elseif  ($avg <= 90) $cd['booking_style'] = 'Mid-range';
            else                 $cd['booking_style'] = 'Early planner';
        }
        unset($cd);

        uasort($countryData, fn($a, $b) => $b['bookings'] <=> $a['bookings']);
        $allCountries = array_values($countryData);

        // Build known countries inline (avoids array_filter allocation)
        $knownCountries = [];
        foreach ($allCountries as $c) {
            if ($c['country'] !== 'Unknown') $knownCountries[] = $c;
        }

        $topCountry     = !empty($knownCountries) ? $knownCountries[0] : (!empty($allCountries) ? $allCountries[0] : null);
        $topCountryName = $topCountry ? $topCountry['country'] : '—';
        $topBookings    = $topCountry ? $topCountry['bookings'] : 0;
        $topTourists    = $topCountry ? $topCountry['tourists'] : 0;
        $topPct         = $totalBookings > 0 ? round($topBookings / $totalBookings * 100, 1) : 0;

        // Single pass: leadSum/Cnt + earliestCountry/latestCountry
        $leadSum         = 0; $leadCnt = 0;
        $maxLead         = -1; $minLead = PHP_INT_MAX;
        $earliestCountry = null; $latestCountry = null;

        foreach ($allCountries as $cd) {
            $leadSum += $cd['avg_lead_days'] * $cd['bookings'];
            $leadCnt += $cd['bookings'];

            if ($cd['country'] === 'Unknown') continue;

            if ($cd['bookings'] >= 3) {
                if ($cd['avg_lead_days'] > $maxLead) { $maxLead = $cd['avg_lead_days']; $earliestCountry = $cd; }
                if ($cd['avg_lead_days'] < $minLead) { $minLead = $cd['avg_lead_days']; $latestCountry   = $cd; }
            }
        }

        // "Other" = every booking that isn't the top country
        $otherBookings = $totalBookings - $topBookings;
        $otherPct      = $totalBookings > 0 ? round($otherBookings / $totalBookings * 100, 1) : 0;
        $overallAvg = $leadCnt > 0 ? (int) round($leadSum / $leadCnt) : 0;

        // Top-15 by bookings for bar chart (include Unknown)
        $top15 = array_slice($allCountries, 0, 15);

        // Top-15 by avg lead days for lead days chart
        $leadSorted = $knownCountries;
        usort($leadSorted, fn($a, $b) => $b['avg_lead_days'] <=> $a['avg_lead_days']);
        $top15Lead = array_slice($leadSorted, 0, 15);

        return [
            'kpis' => [
                'top_country'      => $topCountryName,
                'top_bookings'     => $topBookings,
                'top_tourists'     => $topTourists,
                'top_pct'          => $topPct,
                'other_bookings'   => $otherBookings,
                'other_pct'        => $otherPct,
                'total_countries'  => count($knownCountries),
                'earliest_country' => $earliestCountry ? $earliestCountry['country']      : '—',
                'earliest_days'    => $earliestCountry ? $earliestCountry['avg_lead_days'] : 0,
                'latest_country'   => $latestCountry   ? $latestCountry['country']        : '—',
                'latest_days'      => $latestCountry   ? $latestCountry['avg_lead_days']  : 0,
                'overall_avg_lead' => $overallAvg,
            ],
            'bookings_by_country'  => [
                'labels'   => array_column($top15, 'country'),
                'bookings' => array_column($top15, 'bookings'),
                'tourists' => array_column($top15, 'tourists'),
            ],
            'lead_days_by_country' => [
                'labels'         => array_column($top15Lead, 'country'),
                'lead_days'      => array_column($top15Lead, 'avg_lead_days'),
                'booking_styles' => array_column($top15Lead, 'booking_style'),
            ],
            'lead_distribution' => [
                'labels' => array_keys($leadBuckets),
                'values' => array_values($leadBuckets),
            ],
            'table'         => array_slice($knownCountries, 0, 15),
            'insights'      => self::buildGeoInsights($leadSorted, $topCountry, $topCountryName, $overallAvg),
            'lead_insights' => self::buildLeadInsights($leadBuckets, $totalBookings, $overallAvg),
        ];
    }

    private static function buildLeadInsights(array $buckets, int $totalBookings, int $overallAvg): array
    {
        if ($totalBookings === 0) return [];
        $insights = [];

        // Most common window
        arsort($buckets);
        $topBucket = array_key_first($buckets);
        $topCount  = $buckets[$topBucket];
        $topPct    = round($topCount / $totalBookings * 100, 1);
        $insights[] = $topBucket . ' is the most common window — ' .
                      number_format($topCount) . ' bookings (' . $topPct . '%)';

        // Same-day + 1-7 days combined (last-minute)
        $lastMin    = ($buckets['Same-day'] ?? 0) + ($buckets['1–7 days'] ?? 0);
        $lastMinPct = round($lastMin / $totalBookings * 100, 1);
        if ($lastMin > 0) {
            $insights[] = 'Same-day and 1–7 day bookings together = ' .
                          number_format($lastMin) . ' — ' . $lastMinPct . '% of bookings are last-minute';
        }

        // 180+ days
        $early    = $buckets['180+ days'] ?? 0;
        $earlyPct = round($early / $totalBookings * 100, 1);
        if ($early > 0) {
            $insights[] = '180+ days ahead = ' . number_format($early) .
                          ' bookings — large group operators planning early';
        }

        // Overall average note
        if ($overallAvg > 0) {
            $insights[] = 'Overall average booking window: ' . $overallAvg . ' days';
        }

        return $insights;
    }

    private static function buildGeoInsights(array $leadSorted, ?array $topCountry, string $topCountryName, int $overallAvg): array
    {
        $insights = [];

        // Top 2 earliest planners
        if (count($leadSorted) >= 2) {
            $e1 = $leadSorted[0];
            $e2 = $leadSorted[1];
            $insights[] = $e1['country'] . ' (' . $e1['avg_lead_days'] . ' days) and ' .
                           $e2['country'] . ' (' . $e2['avg_lead_days'] . ' days) book the furthest in advance';
        } elseif (count($leadSorted) === 1) {
            $e1 = $leadSorted[0];
            $insights[] = $e1['country'] . ' (' . $e1['avg_lead_days'] . ' days) books the furthest in advance';
        }

        // 3rd and 4th planners (if enough data)
        if (count($leadSorted) >= 4) {
            $e3 = $leadSorted[2];
            $e4 = $leadSorted[3];
            $insights[] = $e3['country'] . ' (' . $e3['avg_lead_days'] . ' days) and ' .
                           $e4['country'] . ' (' . $e4['avg_lead_days'] . ' days) also plan well ahead';
        } elseif (count($leadSorted) === 3) {
            $e3 = $leadSorted[2];
            $insights[] = $e3['country'] . ' (' . $e3['avg_lead_days'] . ' days) also plans well ahead';
        }

        // Last-minute bookers (ascending by avg_lead_days, min 3 bookings, skip zero)
        $lmCandidates = array_values(array_filter($leadSorted, fn($c) => $c['bookings'] >= 3 && $c['avg_lead_days'] > 0));
        usort($lmCandidates, fn($a, $b) => $a['avg_lead_days'] <=> $b['avg_lead_days']);
        if (count($lmCandidates) >= 3) {
            $l1 = $lmCandidates[0]; $l2 = $lmCandidates[1]; $l3 = $lmCandidates[2];
            $insights[] = $l1['country'] . ' (' . $l1['avg_lead_days'] . ' days), ' .
                           $l2['country'] . ' (' . $l2['avg_lead_days'] . ' days) and ' .
                           $l3['country'] . ' (' . $l3['avg_lead_days'] . ' days) are the most last-minute bookers';
        } elseif (count($lmCandidates) >= 2) {
            $l1 = $lmCandidates[0]; $l2 = $lmCandidates[1];
            $insights[] = $l1['country'] . ' (' . $l1['avg_lead_days'] . ' days) and ' .
                           $l2['country'] . ' (' . $l2['avg_lead_days'] . ' days) are the most last-minute bookers';
        }

        // Top country vs overall average — use the already-resolved $topCountry, no array search needed
        if ($topCountry && $overallAvg > 0) {
            $diff       = abs($topCountry['avg_lead_days'] - $overallAvg);
            $comparison = $diff <= 5
                ? 'close to the overall median of ' . $overallAvg . ' days'
                : ($topCountry['avg_lead_days'] > $overallAvg
                    ? $diff . ' days above the overall average of ' . $overallAvg . ' days'
                    : $diff . ' days below the overall average of ' . $overallAvg . ' days');
            $insights[] = $topCountryName . ' books at ' . $topCountry['avg_lead_days'] . ' days avg — ' . $comparison;
        }

        return $insights;
    }

    // ─────────────────────────────────────────────────────────────
    // AFFILIATES TAB
    // ─────────────────────────────────────────────────────────────
    public static function getAffiliatesData($fromDate, $toDate, $tourPackageIds = [], $paymentTypes = [], $thirdPartyPlatforms = [], $requestFromValues = [], $bookingSources = [])
    {
        $rows = self::getDashboardRows($fromDate, $toDate, $tourPackageIds, $paymentTypes, $thirdPartyPlatforms, $requestFromValues, $bookingSources);
        return self::computeAffiliatesData($rows);
    }

    private static function computeAffiliatesData($rows): array
    {
        $empty = [
            'booking_amount'        => 0, 'refund_amount'        => 0,
            'net_card'              => 0, 'net_cash'             => 0,
            'net_invoiced'          => 0, 'total_net_collected'  => 0,
            'net_tripadvisor'       => 0, 'net_headout'          => 0,
            'net_fareharbor'        => 0, 'total_affiliates'     => 0,
            'total_bookings'        => 0, 'cancellation_bookings'=> 0,
            'table_rows'            => [],
        ];

        $affiliateRows = $rows->filter(fn($r) => isset($r->is_affiliate) && $r->is_affiliate === 'Yes');

        if ($affiliateRows->isEmpty()) {
            return $empty;
        }

        $totalAffiliates    = $affiliateRows->pluck('customer_id')->unique()->filter()->count();
        $bookingAmount      = 0;
        $cancellationAmount = 0;
        $refundAmount       = 0;
        $netCard            = 0;
        $netCash            = 0;
        $netInvoiced        = 0;
        $netTripAdvisor       = 0;
        $netHeadout           = 0;
        $netFareHarbor        = 0;
        $seenBookings         = [];
        $cancellationBookings = [];
        $tableRows            = [];

        foreach ($affiliateRows as $row) {
            $bid = $row->tour_booking_id;
            if (!isset($seenBookings[$bid])) {
                $seenBookings[$bid] = true;
                $tableRows[$bid] = [
                    'id'             => $bid,
                    'name'           => $row->customer_name,
                    'package'        => $row->package_name,
                    'tourists'       => $row->total_pax,
                    'booking_status' => $row->booking_status,
                    'via'            => $row->via,
                    'booking_amount' => 0,
                    'refund_amount'  => 0,
                ];
            }

            $via = strtolower($row->via ?? '');

            if ($row->status === 'Completed') {
                $bookingAmount += $row->amount;
                if (str_contains($via, 'card')) {
                    $netCard += $row->amount;
                } elseif ($via === 'cash') {
                    $netCash += $row->amount;
                } elseif (str_contains($via, 'invoice')) {
                    $netInvoiced += $row->amount;
                } elseif ($via === 'tripadvisor') {
                    $netTripAdvisor += $row->amount;
                } elseif ($via === 'headout') {
                    $netHeadout += $row->amount;
                } elseif ($via === 'fareharbor') {
                    $netFareHarbor += $row->amount;
                }
            }

            if ($row->status === 'Refund' && $row->booking_status === 'Booked') {
                $refundAmount += $row->amount;
                if (str_contains($via, 'invoice')) {
                    $netInvoiced -= $row->amount;
                } elseif ($via === 'tripadvisor') {
                    $netTripAdvisor -= $row->amount;
                } elseif ($via === 'headout') {
                    $netHeadout -= $row->amount;
                } elseif ($via === 'fareharbor') {
                    $netFareHarbor -= $row->amount;
                }
            }

            if ($row->status === 'Refund' && $row->booking_status === 'Cancelled') {
                $cancellationBookings[$bid] = true;
                $cancellationAmount += $row->amount;
            }

            if ($row->status === 'Completed') {
                $tableRows[$bid]['booking_amount'] += $row->amount;
            }
            if ($row->status === 'Refund') {
                $tableRows[$bid]['refund_amount'] += $row->amount;
            }
        }

        $tableRows = array_values(array_map(function ($r) {
            $r['booking_amount'] = round($r['booking_amount'], 2);
            $r['refund_amount']  = round($r['refund_amount'], 2);
            $r['paid_amount']    = round($r['booking_amount'] - $r['refund_amount'], 2);
            return $r;
        }, $tableRows));
        $totalNetCollected = round($bookingAmount - $refundAmount - $cancellationAmount, 2);

        return [
            'booking_amount'      => self::formatCurrencyShort(round($bookingAmount, 2)),
            'refund_amount'       => self::formatCurrencyShort(round($refundAmount + $cancellationAmount, 2)),
            'net_card'            => self::formatCurrencyShort(round($netCard, 2)),
            'net_cash'            => self::formatCurrencyShort(round($netCash, 2)),
            'net_invoiced'        => self::formatCurrencyShort(round($netInvoiced, 2)),
            'total_net_collected' => self::formatCurrencyShort($totalNetCollected),
            'net_tripadvisor'     => self::formatCurrencyShort(round($netTripAdvisor, 2)),
            'net_headout'         => self::formatCurrencyShort(round($netHeadout, 2)),
            'net_fareharbor'      => self::formatCurrencyShort(round($netFareHarbor, 2)),
            'total_affiliates'      => $totalAffiliates,
            'total_bookings'        => count($seenBookings),
            'cancellation_bookings' => count($cancellationBookings),
            'table_rows'            => $tableRows,
        ];
    }

}
