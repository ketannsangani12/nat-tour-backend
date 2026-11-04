<?php

namespace App\Services\Calendar;

use App\Constants\Constants;
use App\Models\PackageAffiliateAssignment;
use App\Utils\CalendarWeekFilterUtils;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class CalendarQueryService
{
    public static function generateQueries(string $userType, string $dateRangeSql = ''): array
    {
        $affiliateJoinQuery = '';
        $staffJoinQuery = '';
        $affiliateSeats = '';
        $showAllPackageBookings = 1;

        if ($userType === 'Affiliate') {
            // For affiliates, we need to support both affiliate-specific and normal logic per package
            // Always use affiliate-specific queries to get both afl_tourists/afl_seats and regular data
            // The calendar logic will decide per package which data to use based on show_all_package_bookings

            $affiliateSeats = 'IFNULL(ast.seat_limit, 0) as afl_seats,';
            $affiliateJoinQuery = self::generateAffiliateJoinQuery();

            $touristsJoin = self::generateAffiliateTouristsJoin($dateRangeSql);
            $affiliateTouristsSelect = $touristsJoin['select'];
            $affiliateTouristsJoin   = $touristsJoin['join'];

            $bookedJoin = self::generateAffiliateBookedSeatsJoin($dateRangeSql);
            $affiliateBookedSeatsSelect = $bookedJoin['select'];
            $affiliateBookedSeatsJoin   = $bookedJoin['join'];

            // Set showAllPackageBookings to 0 to ensure affiliate logic is used in calendar processing
            // Individual package decisions will be made in the calendar method based on package assignments
            $showAllPackageBookings = Constants::STATUS_ZERO;
        } elseif (self::hasDepartmentPackage()) {
            $staffJoinQuery = self::generateStaffJoinQuery();
        }

        return [
            'affiliateJoinQuery'        => $affiliateJoinQuery,
            'affiliateTouristsSelect'   => $affiliateTouristsSelect ?? '',
            'affiliateTouristsJoin'     => $affiliateTouristsJoin ?? '',
            'affiliateBookedSeatsSelect' => $affiliateBookedSeatsSelect ?? '',
            'affiliateBookedSeatsJoin'  => $affiliateBookedSeatsJoin ?? '',
            'affiliateSeats'            => $affiliateSeats,
            'staffJoinQuery'            => $staffJoinQuery,
            'showAllPackageBookings'    => $showAllPackageBookings,
        ];
    }

    private static function generateAffiliateJoinQuery(): string
    {
        // paa is the only join that should filter which packages come back -- it scopes
        // the calendar to packages this affiliate is assigned to. tsta/ast supply the
        // per-slot seat limit for affiliates restricted to specific seats
        // (show_all_package_bookings == 0), but affiliates with show_all_package_bookings
        // == 1 are never given tsta/ast rows since they don't need a per-slot limit at
        // all. Those must stay LEFT JOINs -- as INNER JOINs they silently dropped every
        // row for any affiliate lacking that per-slot config, emptying their calendar
        // entirely. IFNULL(ast.seat_limit, 0) already handles the missing-match case.
        // tsta.tour_package_id must be pinned to this row's own package -- time_from/
        // time_to alone are not unique across packages (many packages reuse the same
        // clock times), so without it this join fans out onto every other package's
        // tsta row for the same time_from/time_to, multiplying tbm.tourists in the SUM.
        return 'INNER JOIN package_affiliate_assignments paa ON paa.package_id = tp.id
                AND paa.affiliate_id = ' . Auth::id() . '
            LEFT JOIN tour_slot_times_affiliate tsta ON tsta.time_from = tst.time_from
                AND tsta.time_to = tst.time_to
                AND tsta.tour_package_id = tst.tour_package_id
                AND tsta.status = "Active"
                AND tsta.deleted_by = 0
            LEFT JOIN affiliate_slot_times ast ON ast.tour_slot_time_id = tsta.id
                AND ast.affiliate_id = ' . Auth::id();
    }

    private static function generateAffiliateTouristsJoin(string $dateRangeSql = ''): array
    {
        $affiliateId = Auth::id();
        $dateWhere   = $dateRangeSql !== '' ? "WHERE {$dateRangeSql}" : 'WHERE 1=1';
        return [
            'select' => 'IFNULL(afl_agg.afl_tourists, 0) AS afl_tourists,',
            // Drive from tsd2 (date BETWEEN is selective) → tst2 → tbm2.
            // customer_id = $affiliateId on the join makes the customers table redundant.
            'join'   => "LEFT JOIN (
                SELECT tst2.id AS tour_slot_id, tbm2.tour_package_id, SUM(tbm2.tourists) AS afl_tourists
                FROM tour_slot_dates AS tsd2
                INNER JOIN tour_slot_times AS tst2 ON tst2.tour_slot_id = tsd2.id
                INNER JOIN tour_bookings_master AS tbm2 ON tbm2.tour_slot_id = tst2.id
                    AND tbm2.status != 'Cancelled'
                    AND tbm2.customer_id = $affiliateId
                {$dateWhere}
                GROUP BY tst2.id, tbm2.tour_package_id
            ) AS afl_agg ON afl_agg.tour_slot_id = tst.id AND afl_agg.tour_package_id = tp.id",
        ];
    }

    private static function generateAffiliateBookedSeatsJoin(string $dateRangeSql = ''): array
    {
        $dateWhere = $dateRangeSql !== '' ? "WHERE {$dateRangeSql}" : 'WHERE 1=1';
        return [
            'select' => 'IFNULL(booked_agg.booked_seats, 0) AS booked_seats,',
            // Drive from tsd2 (date BETWEEN is selective) → tst2 → tbm2.
            // Starting from tbm2 with status != 'Cancelled' scans the entire bookings table.
            'join'   => "LEFT JOIN (
                SELECT tst2.id AS tour_slot_id, SUM(tbm2.tourists) AS booked_seats
                FROM tour_slot_dates AS tsd2
                INNER JOIN tour_slot_times AS tst2 ON tst2.tour_slot_id = tsd2.id
                INNER JOIN tour_bookings_master AS tbm2 ON tbm2.tour_slot_id = tst2.id
                    AND tbm2.status != 'Cancelled'
                {$dateWhere}
                GROUP BY tst2.id
            ) AS booked_agg ON booked_agg.tour_slot_id = tst.id",
        ];
    }

    private static function generateStaffJoinQuery(): string
    {
        return 'INNER JOIN department_package dp ON dp.package_id = tp.id
                AND dp.department_id = ' . Auth::user()->department_id;
    }

    private static function hasDepartmentPackage(): bool
    {
        return function_exists('departmentPackage') && departmentPackage();
    }

    public static function generateGroupIndexQuery($packageId, $date): string
    {
        $year = Carbon::parse($date)->format('Y');
        $query = "IFNULL((SELECT tpf.group_index
            FROM tour_package_families AS tpf
            WHERE tpf.tour_package_id = tsd.tour_package_id
                AND tst.time_from = tpf.time_from
                AND tpf.relation_year = $year";

        if (!empty($packageId) && !in_array(0, $packageId)) {
            $query .= " AND tpf.tour_package_id IN (" . implode(',', $packageId) . ")";
        }

        return $query . " GROUP BY tpf.tour_package_id), 0) AS group_index";
    }

    public static function generateIsParentQuery($packageId, $date): string
    {
        $year = Carbon::parse($date)->format('Y');
        $query = "IFNULL((SELECT tpf.is_parent
            FROM tour_package_families AS tpf
            WHERE tpf.tour_package_id = tsd.tour_package_id
                AND tst.time_from = tpf.time_from
                AND tpf.relation_year = $year";

        if (!empty($packageId) && !in_array(0, $packageId)) {
            $query .= " AND tpf.tour_package_id IN (" . implode(',', $packageId) . ")";
        }

        return $query . " GROUP BY tpf.tour_package_id), 0) AS is_parent";
    }

    public static function fetchDateSlots($userType, $option, $date, $tourOpId, $requestMonth, $requestYear, $weekNumber, $packageId): array
    {
        $cacheKey = self::buildDateSlotsCacheKey($userType, $option, $date, $tourOpId, $requestMonth, $requestYear, $weekNumber, $packageId);

        // Short TTL: this is the calendar *display* query only. The real seat-availability
        // gate that booking creation actually checks (CalendarService::getReservedSeats())
        // is a separate, uncached, real-time query -- so a short-lived stale read here can
        // only show a slightly out-of-date seat count, never oversell a slot.
        return Cache::remember($cacheKey, 25, function () use ($userType, $option, $date, $tourOpId, $requestMonth, $requestYear, $weekNumber, $packageId) {
            return self::fetchDateSlotsUncached($userType, $option, $date, $tourOpId, $requestMonth, $requestYear, $weekNumber, $packageId);
        });
    }

    private static function buildDateSlotsCacheKey($userType, $option, $date, $tourOpId, $requestMonth, $requestYear, $weekNumber, $packageId): string
    {
        $packageIdKey = is_array($packageId) ? implode('-', $packageId) : (string) $packageId;

        // Auth::id() must be part of the key: for Affiliates, generateAffiliateJoinQuery()/
        // generateAffiliateTouristsJoin()/generateAffiliateBookedSeatsJoin() bake the current
        // affiliate's own id into the SQL, and for staff on a department package,
        // generateStaffJoinQuery() bakes in Auth::user()->department_id (1:1 with the staff
        // member). Keying on Auth::id() alone keeps both cases correctly isolated per user;
        // tenant isolation itself is handled separately/automatically by Stancl Tenancy's
        // CacheTenancyBootstrapper (see config/tenancy.php 'cache.tag_base').
        $authId = Auth::id() ?? 'guest';

        return implode('_', [
            'calendar_date_slots',
            $userType,
            $authId,
            $option,
            $date,
            $tourOpId,
            $requestMonth,
            $requestYear,
            $weekNumber,
            $packageIdKey,
        ]);
    }

    private static function fetchDateSlotsUncached($userType, $option, $date, $tourOpId, $requestMonth, $requestYear, $weekNumber, $packageId): array
    {
        // Compute the date filter first so subqueries can be scoped to the visible date range
        $lookup = self::addDateFilter($option, $date, $requestMonth ?? null, $requestYear ?? null, $weekNumber ?? null);
        // Rewrite tsd.date → tsd2.date for use inside derived-table subqueries
        $dateRangeSql = str_replace('tsd.date', 'tsd2.date', $lookup['query']);

        $affiliateJoinQueries = self::generateQueries($userType, $dateRangeSql);
        $affiliateSeats             = $affiliateJoinQueries['affiliateSeats'];
        $affiliateJoinQuery         = $affiliateJoinQueries['affiliateJoinQuery'];
        $affiliateTouristsSelect    = $affiliateJoinQueries['affiliateTouristsSelect'];
        $affiliateTouristsJoin      = $affiliateJoinQueries['affiliateTouristsJoin'];
        $affiliateBookedSeatsSelect = $affiliateJoinQueries['affiliateBookedSeatsSelect'];
        $affiliateBookedSeatsJoin   = $affiliateJoinQueries['affiliateBookedSeatsJoin'];
        $staffJoinQuery             = $affiliateJoinQueries['staffJoinQuery'];

        // OPTIMIZATION: Use LEFT JOIN instead of subqueries for better performance
        $year = Carbon::parse($date)->format('Y');
        $packageFilter = "";
        if (!empty($packageId) && !in_array(0, $packageId)) {
            $packageFilter = " AND tpf.tour_package_id IN (" . implode(',', $packageId) . ")";
        }

        $dateSlotsQuery = "SELECT
                tsd.id,
                tsd.date,
                tst.time_from,
                tp.id AS tour_package_id,
                tp.name AS pkg_name,
                IFNULL(SUM(tbm.tourists), 0) AS tourists,
                IFNULL(tst.time_slot_seats, 0) as seats,
                $affiliateTouristsSelect
                $affiliateSeats
                $affiliateBookedSeatsSelect
                tst.id AS time_slot_id,
                1 AS available_slots,
                IFNULL(tpf.group_index, 0) AS group_index,
                IFNULL(tpf.is_parent, 0) AS is_parent
            FROM
                tour_slot_dates AS tsd
                INNER JOIN tour_packages AS tp ON tp.id = tsd.tour_package_id
                INNER JOIN tour_slot_times AS tst ON tst.tour_slot_id = tsd.id
                LEFT JOIN tour_bookings_master AS tbm ON tbm.tour_slot_id = tst.id
                    AND tbm.tour_package_id = tsd.tour_package_id
                    AND tbm.status != 'Cancelled'
                LEFT JOIN tour_package_families AS tpf ON tpf.tour_package_id = tsd.tour_package_id
                    AND tst.time_from = tpf.time_from
                    AND tpf.relation_year = $year
                    $packageFilter
                $affiliateJoinQuery
                $affiliateTouristsJoin
                $affiliateBookedSeatsJoin
                $staffJoinQuery
            WHERE ";

        $dateSlotsQuery .= $lookup['query'];

        $dateSlotsQuery .= " AND tp.tour_operator_id = $tourOpId
                AND tp.status = 'Active'
                AND tp.deleted_by = 0
                AND (
                    (tsd.status = 'Active' AND tsd.deleted_by = 0 AND tst.deleted_by = 0)
                    OR EXISTS (
                        SELECT 1 FROM tour_bookings_master tbm_chk
                        WHERE tbm_chk.tour_slot_id = tst.id
                          AND tbm_chk.tour_package_id = tsd.tour_package_id
                          AND tbm_chk.status = 'Booked'
                    )
                ) ";

        if ($packageId != "" && !in_array(0, $packageId)) {
            $i = implode(',', $packageId);
            $dateSlotsQuery .= "AND tp.id IN ($i) ";
        }

        $dateSlotsQuery .= "GROUP BY
            tsd.id,
            tst.id,
            tpf.group_index,
            tpf.is_parent
        ORDER BY
            tsd.date ASC,
            tp.Order_by ASC,
            tst.id ASC";

        $data = DB::select($dateSlotsQuery);

        // For affiliates, also load related packages (child/parent packages) to ensure proper availability calculation
        if ($userType === 'Affiliate' && !empty($data)) {
            $assignedPackageIds = array_unique(array_column($data, 'tour_package_id'));

            if (!empty($assignedPackageIds)) {
                $assignedPackageIdsStr = implode(',', $assignedPackageIds);

                // Get related packages for the assigned packages - only load packages with same parent_package_id and time_from
                // This ensures we only get true parent-child relationships for the same time slots
                $relatedPackagesQuery = "SELECT DISTINCT tpf_related.tour_package_id
                    FROM tour_package_families tpf_assigned
                    JOIN tour_package_families tpf_related ON tpf_related.parent_package_id = tpf_assigned.parent_package_id
                        AND tpf_related.relation_year = tpf_assigned.relation_year
                        AND tpf_related.time_from = tpf_assigned.time_from
                    WHERE tpf_assigned.tour_package_id IN ($assignedPackageIdsStr)
                        AND tpf_related.tour_package_id NOT IN ($assignedPackageIdsStr)
                        AND tpf_assigned.relation_year = $year";

                $relatedPackages = DB::select($relatedPackagesQuery);
                $relatedPackageIds = array_column($relatedPackages, 'tour_package_id');

                if (!empty($relatedPackageIds)) {
                    $relatedPackageIdsStr = implode(',', $relatedPackageIds);

                    // Load related packages data (without affiliate-specific joins)
                    $relatedDataQuery = "SELECT
                            tsd.id,
                            tsd.date,
                            tst.time_from,
                            tp.id AS tour_package_id,
                            tp.name AS pkg_name,
                            IFNULL(SUM(tbm.tourists), 0) AS tourists,
                            IFNULL(tst.time_slot_seats, 0) as seats,
                            0 AS afl_tourists,
                            0 AS afl_seats,
                            0 AS booked_seats,
                            tst.id AS time_slot_id,
                            1 AS available_slots,
                            IFNULL(tpf.group_index, 0) AS group_index,
                            IFNULL(tpf.is_parent, 0) AS is_parent
                        FROM
                            tour_slot_dates AS tsd
                            INNER JOIN tour_packages AS tp ON tp.id = tsd.tour_package_id
                            INNER JOIN tour_slot_times AS tst ON tst.tour_slot_id = tsd.id
                            LEFT JOIN tour_bookings_master AS tbm ON tbm.tour_slot_id = tst.id
                                AND tbm.tour_package_id = tsd.tour_package_id
                                AND tbm.status != 'Cancelled'
                            LEFT JOIN tour_package_families AS tpf ON tpf.tour_package_id = tsd.tour_package_id
                                AND tst.time_from = tpf.time_from
                                AND tpf.relation_year = $year
                                $packageFilter
                        WHERE ";

                    $relatedDataQuery .= $lookup['query'];
                    $relatedDataQuery .= " AND tp.tour_operator_id = $tourOpId
                            AND tp.status = 'Active'
                            AND tp.deleted_by = 0
                            AND (
                                (tsd.status = 'Active' AND tsd.deleted_by = 0 AND tst.deleted_by = 0)
                                OR EXISTS (
                                    SELECT 1 FROM tour_bookings_master tbm_chk
                                    WHERE tbm_chk.tour_slot_id = tst.id
                                      AND tbm_chk.tour_package_id = tsd.tour_package_id
                                      AND tbm_chk.status = 'Booked'
                                )
                            )
                            AND tp.id IN ($relatedPackageIdsStr) ";

                    if ($packageId != "" && !in_array(0, $packageId)) {
                        $i = implode(',', $packageId);
                        $relatedDataQuery .= "AND tp.id IN ($i) ";
                    }

                    $relatedDataQuery .= "GROUP BY
                        tsd.id,
                        tst.id,
                        tpf.group_index,
                        tpf.is_parent
                    ORDER BY
                        tsd.date ASC,
                        tp.Order_by ASC,
                        tst.id ASC";

                    $relatedData = DB::select($relatedDataQuery);

                    // Merge related data with main data
                    $data = array_merge($data, $relatedData);
                }
            }
        }

        return [
            'data' => $data,
            'weekDates' => $lookup['weekDates'],
            // Exposed so callers can run their own follow-up queries (e.g. fetching a
            // family member missing from $data) scoped to the same visible date range.
            'dateRangeQuery' => $lookup['query'],
        ];
    }
    private static function addDateFilter($option, $date, $requestMonth = null, $requestYear = null, $weekNumber = null): array
    {
        $lookup = [
            'query' => '',
            'weekDates' => [],
        ];

        if ($option === 'month') {
            $lookup['query'] = "tsd.date BETWEEN CAST('$date' AS DATE) AND LAST_DAY(CAST('$date' AS DATE))";
            return $lookup;
        }

        if ($option === 'week') {
            $weekWithDatesOfMonth = CalendarWeekFilterUtils::weekOfMonth($requestMonth, $requestYear);
            $weekDatesArray = CalendarWeekFilterUtils::getFirstToLastDateOfWeek($weekWithDatesOfMonth, $weekNumber);

            $lookup['weekDates'] = $weekDatesArray['weekDates'];
            $lookup['query'] = "tsd.date BETWEEN CAST('{$weekDatesArray['weekStartDate']}' AS DATE) AND CAST('{$weekDatesArray['weekEndDate']}' AS DATE)";
        }

        return $lookup;
    }

    public static function fetchBookings(string $userType, int $showAllPackageBookings): array
    {
        $affiliateWhereSql = self::setAffiliateConditions($userType, $showAllPackageBookings);
        $staffWhereSql = self::setStaffConditions();

        // Step 1: cheaply fetch the IDs of the 25 most recent matching bookings.
        // No join to tour_packages here — the tp join forces MySQL to abandon the created_at
        // index in favour of a full 300k+ row scan. Affiliate/staff filters reference
        // tbm.tour_package_id directly so no join is needed.
        $idRows = DB::select(
            "SELECT id, cart_id
            FROM tour_bookings_master
            WHERE 1=1 {$affiliateWhereSql} {$staffWhereSql}
            ORDER BY created_at DESC
            LIMIT 25"
        );

        if (empty($idRows)) {
            return [];
        }

        $ids = implode(',', array_column($idRows, 'id'));

        // Scope the cart-count subquery to only the cart_ids we actually need.
        // cart_id is a string token, so values must be quoted in the IN list.
        $cartIdValues = array_unique(array_filter(array_column($idRows, 'cart_id')));
        $cartIdFilter = !empty($cartIdValues)
            ? "WHERE cart_id IN ('" . implode("','", array_map('addslashes', $cartIdValues)) . "')"
            : 'WHERE 1=0';

        // Step 2: load full details for those 25 bookings only.
        // All derived tables are scoped to $ids so they scan at most 25 rows each.
        return DB::select(
            "SELECT
                tbm.id,
                tbm.cart_id,
                tbm.group_id,
                tbm.is_group_booking,
                tbm.tourists,
                tbm.infants,
                tbm.viator_bookingRef as tripadvisor_id,
                tbm.get_your_guide_reference_booking as get_your_guide_booking_id,
                tbm.ota_reference_booking as ota_booking_id,
                tst.date,
                tbm.name as cust_name,
                c.is_affiliate,
                tp.name as pkg_name,
                tbm.email as cust_email,
                tbm.total,
                tbm.created_at as booking_d_t,
                t_op.timezone,
                tbm.status,
                tbm.is_reserved_for_affiliate,
                tbm.reference_booking,
                COALESCE(grouped.cart_booking_count, 1) as cartBookingsCount,
                tp.package_has_slots,
                tp.package_has_waiver,
                tp.package_has_permit,
                tst.time_from,
                tst.time_to,
                CONCAT(tsd.date, ' ', tst.time_from) AS tour_d_t,
                COALESCE(trans_paid.paid, 0) AS paid,
                trans_split.paid_split AS paid_split,
                trans_method.payment_method,
                COALESCE(rate_groups.t_groups, '') AS t_groups,
                IF(ib.invoice_id IS NOT NULL, i.status, '0') AS invoice_status,
                IF(tbm.viator_bookingRef IS NOT NULL, 'Yes', 'No') AS viator_bookingRef,
                IF(tbm.get_your_guide_reference_booking IS NOT NULL, 'Yes', 'No') AS get_your_guide_reference_booking,
                IF(tbm.ota_reference_booking IS NOT NULL, 'Yes', 'No') AS ota_reference_booking
            FROM
                tour_bookings_master AS tbm
                INNER JOIN customers AS c ON c.id = tbm.customer_id
                INNER JOIN tour_packages AS tp ON tp.id = tbm.tour_package_id
                INNER JOIN tour_slot_times AS tst ON tst.id = tbm.tour_slot_id
                INNER JOIN tour_operators AS t_op ON t_op.id = tp.tour_operator_id
                INNER JOIN tour_slot_dates AS tsd ON tsd.id = tst.tour_slot_id
                LEFT JOIN invoice_bookings AS ib ON ib.tour_booking_id = tbm.id
                LEFT JOIN invoices AS i ON i.id = ib.invoice_id
                LEFT JOIN
                    (SELECT cart_id, COUNT(*) as cart_booking_count
                    FROM tour_bookings_master
                    {$cartIdFilter}
                    GROUP BY cart_id) as grouped
                ON tbm.cart_id = grouped.cart_id
                LEFT JOIN
                    (SELECT tour_booking_id, SUM(amount) as paid
                    FROM transactions
                    WHERE status = 'Completed' AND tour_booking_id IN ($ids)
                    GROUP BY tour_booking_id) as trans_paid
                ON trans_paid.tour_booking_id = tbm.id
                LEFT JOIN
                    (SELECT tour_booking_id, SUM(amount) as paid_split
                    FROM transactions
                    WHERE status = 'Completed' AND payment_type = '1' AND tour_booking_id IN ($ids)
                    GROUP BY tour_booking_id) as trans_split
                ON trans_split.tour_booking_id = tbm.id
                LEFT JOIN
                    (SELECT tour_booking_id, via as payment_method
                    FROM transactions
                    WHERE status = 'Completed' AND tour_booking_id IN ($ids)
                    GROUP BY tour_booking_id) as trans_method
                ON trans_method.tour_booking_id = tbm.id
                LEFT JOIN
                    (SELECT tbt.tour_booking_id,
                        GROUP_CONCAT(
                        CASE
                        WHEN tbt.is_infant_rate = 1
                        THEN
                        CONCAT(LEFT(tpr.rate_for, 1), ':', tbt.infants,'^',tpr.rate_for)
                        ELSE
                        CONCAT(LEFT(tpr.rate_for, 1), ':', tbt.tourists,'^',tpr.rate_for)
                        END
                        ORDER BY tpr.age_from DESC, tpr.age_to DESC SEPARATOR ' | ') as t_groups
                    FROM tour_bookings_transact as tbt
                    INNER JOIN tour_package_rates as tpr ON tpr.id = tbt.tour_package_rate_id
                    WHERE tbt.deleted_by = 0 AND tbt.tour_booking_id IN ($ids)
                    GROUP BY tbt.tour_booking_id) as rate_groups
                ON rate_groups.tour_booking_id = tbm.id
            WHERE
                tbm.id IN ($ids)
                AND tp.status = 'Active'
            ORDER BY
                tbm.created_at DESC"
        );
    }

    private static function setAffiliateConditions(string $userType, int $showAllPackageBookings)
    {
        $affiliateWhereSql = '';
        if ($userType === 'Affiliate') {
            $affiliateId = Auth::id();

            // Always do a live lookup — generateQueries() hardcodes showAllPackageBookings=0
            // for the calendar slot display loop, so the passed value cannot be trusted here.
            $showAllPackageIds = PackageAffiliateAssignment::where('affiliate_id', $affiliateId)
                ->where('show_all_package_bookings', Constants::STATUS_ONE)
                ->pluck('package_id')
                ->toArray();

            if (!empty($showAllPackageIds)) {
                $packageIdsStr = implode(',', $showAllPackageIds);
                // Show all bookings (including admin-created) for packages with show_all=1,
                // plus the affiliate's own bookings for any other package.
                $affiliateWhereSql = "AND (tour_package_id IN ($packageIdsStr) OR customer_id = $affiliateId)";
            } else {
                $affiliateWhereSql = "AND customer_id = $affiliateId";
            }
        }
        return $affiliateWhereSql;
    }

    private static function setStaffConditions()
    {
        $staffWhereSql = '';
        $departmentPackages = self::getDepartmentPackages();
        if (!empty($departmentPackages)) {
            $assignedPackageIdsString = implode(",", $departmentPackages);
            $staffWhereSql = "AND tour_package_id IN ($assignedPackageIdsString)";
        }
        return $staffWhereSql;
    }

    private static function getDepartmentPackages()
    {
        // Assuming departmentPackage() is a global helper — consider moving it into a service.
        return departmentPackage() ?? [];
    }
}
