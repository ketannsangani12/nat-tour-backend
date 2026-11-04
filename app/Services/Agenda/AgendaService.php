<?php

namespace App\Services\Agenda;

use App\Constants\Constants;
use App\Models\Customer;
use App\Models\TourBooking;
use App\Models\TourPackage;
use App\Services\Booking\BundlePackageBookingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AgendaService
{
    /**
     * Summary of getCheckinsStatus
     * @param mixed $item
     * @param mixed $tourBookingTransact
     * @return void
     */
    public static function getCheckinsStatus($item, $tourBookingTransact, $tenant = '')
    {
        $packageWiseRedeemedTickets = 0; // Package wise redeemed tickets
        $packageWiseNotRedeemedTickets = 0; // Package wise not redeemed tickets
        $item->redeemed_tickets = 0; // booking wise total redeemed tickets
        $item->not_redeemed_tickets = 0; // booking wise total not redeemed tickets
        $item->bookingid = isset($item->booking_id) ? $item->booking_id : $item->bookingid;
        $item->packageid = isset($item->tour_package_id) ? $item->tour_package_id : $item->packageid;
        $item->is_check_in = isset($item->checkin) ? $item->checkin : $item->is_check_in;
        $item->touristsAgeInfo = [];
        $item->groupRates = [];
        $item->checkInsTotal = 0;
        $item->notCheckInsTotal = 0;
        $item->allNotCheckedIn = 0;
        $item->allFirstCheckedIn = 0;
        $item->checkInsCount = [
            '1' => 0, // 1st Check In
            '2' => 0, // 2nd Check In
            'A' => 0, // Absent
        ];

        if (isset($tourBookingTransact[$item->bookingid])) {
            foreach ($tourBookingTransact[$item->bookingid] as $key => $transac) {
                /* Rate Groups Calculation Start */
                $touristsAgeInfo = [];
                $groupRates = [];

                if ($item->is_group_booking) {
                    $transac->rate_for = 'Person(s)';
                }

                $ageGroupTitle = $transac->rate_for;
                if (!isset($groupRates[$transac->rate_for])) {
                    $groupRates[$ageGroupTitle] = 0;
                }

                $groupRates[$ageGroupTitle] = ($transac->infants > 0) ? $transac->infants :$transac->tourists;
                $touristsAgeInfo[] = ($transac->infants > 0) ? $transac->infants . ' ' . $ageGroupTitle : $transac->tourists . ' ' . $ageGroupTitle;

                foreach ($touristsAgeInfo as $keys => $ageInfo) {
                    $item->touristsAgeInfo[$key] = $ageInfo;
                }

                foreach ($groupRates as $keys => $groupRate) {
                    $item->groupRates[$keys] = $groupRate;
                }
                /* Rate Groups Calculation End */

                if ($item->is_check_in) {
                    if ($item->checkin_type) {
                        $tickets = self::tickets($item, $transac, $packageWiseRedeemedTickets, $packageWiseNotRedeemedTickets, $tenant);

                        $item->redeemed_tickets += $tickets['packageWiseRedeemedTickets']; // Total Redeemed Tickets Of Booking
                        $item->not_redeemed_tickets += $tickets['packageWiseNotRedeemedTickets']; // Total Not Redeemed Tickets Of Booking
                    } else {
                        self::checkIns($item, $transac);
                    }
                }
            }
        }
    }

    /**
     * Summary of checkIns
     * @param mixed $item
     * @param mixed $transac
     * @return void
     */
    public static function checkIns($item, $transac)
    {
        $bookingCheckIn = '';
        $bookingCheckInsCount = ['1' => 0, '2' => 0, 'A' => 0];

        if ($item->package_has_waiver) {
            if ($transac->check_in_status == '') {
                $bookingCheckIn = 'Not Checked In';
            } else {
                $checkIns = (array) json_decode($transac->check_in_status);
                $groupCheckInsCount = ['1' => 0, '2' => 0, 'A' => 0];
                $touristsCount = ($transac->infants > 0) ? $transac->infants : $transac->tourists;
                for ($c = 0, $cc = $touristsCount; $c < $cc; $c++) {
                    if (isset($checkIns[$c]->status) && isset($transac->tourists) && $checkIns[$c]->status != 'N' && $c <= $touristsCount) {
                        $groupCheckInsCount[$checkIns[$c]->status]++;
                        $bookingCheckInsCount[$checkIns[$c]->status]++;
                        $item->checkInsCount[$checkIns[$c]->status]++;
                    }
                }

                if (isset($groupCheckInsCount['1']) && $groupCheckInsCount['1'] == $touristsCount) {
                    $bookingCheckIn = '<div style="border: 1px solid var(--bs-success); background-color: #19875430">' . 'All 1st Check In' . '</div>';
                } elseif (isset($groupCheckInsCount['2']) && $groupCheckInsCount['2'] == $touristsCount) {
                    $bookingCheckIn = '<div style="border: 1px solid var(--bs-info); background-color: #0dcaf020">' . 'All 2nd Check In' . '</div>';
                } elseif (isset($groupCheckInsCount['A']) && $groupCheckInsCount['A'] == $touristsCount) {
                    $bookingCheckIn = '<div style="border: 1px solid var(--bs-danger); background-color: #dc354520">' . 'All Absent' . '</div>';
                }
            }
        }

        if (isset($bookingCheckInsCount['1'])) {
            $item->checkInsTotal += $bookingCheckInsCount['1'];
        }

        if (isset($bookingCheckInsCount['2'])) {
            $item->checkInsTotal += $bookingCheckInsCount['2'];
        }

        if (isset($bookingCheckInsCount['A'])) {
            $item->checkInsTotal += $bookingCheckInsCount['A'];
        }

        $item->bookingCheckIn = $bookingCheckIn;
        $item->notCheckInsTotal = $item->tourists + $item->infants - $item->checkInsTotal;
        $item->allNotCheckedIn = (isset($bookingCheckInsCount['1']) && isset($bookingCheckInsCount['2']) && isset($bookingCheckInsCount['A']) && $bookingCheckInsCount['1'] == 0 && $bookingCheckInsCount['2'] == 0 && $bookingCheckInsCount['A'] == 0) ? 1 : 0;
        $item->allFirstCheckedIn = ($bookingCheckInsCount['1'] == ($item->tourists + $item->infants)) ? 1 : 0;
    }

    /**
     * Summary of tickets
     * @param mixed $item
     * @param mixed $transac
     * @param mixed $packageWiseRedeemedTickets
     * @param mixed $packageWiseNotRedeemedTickets
     * @return array
     */
    public static function tickets($item, $transac, $packageWiseRedeemedTickets, $packageWiseNotRedeemedTickets, $tenant)
    {
        /* Tickets Calculation Start */
        $packageWiseTickets = [];

        // Get packages for the bundle booking
        $packages = BundlePackageBookingService::getBundlePackageBookings($item->bundle_package, $item->bookingid, $item->packageid, $tenant);

        // Decode the redeem_ticket field if it exists
        $redeemTicket = !empty($transac->redeem_ticket) ? json_decode($transac->redeem_ticket, true) : [];

        // Loop through package
        for ($a = 0; $a < count($packages); $a++) {
            $packageWiseTickets[$a] = (object) [
                'id' => $packages[$a]->tour_package_id,
                'name' => $packages[$a]->package_name,
                'class' => '',
                'af_class' => '',
                'not_redeemed' => 0,
                'redeemed' => 0,
                'total' => 0
            ];

            for ($b = 0; $b < ($transac->infants > 0 ? $transac->infants : $transac->tourists); $b++) {
                if (empty($redeemTicket)) {
                    $packageWiseTickets[$a]->not_redeemed++;
                } else {
                    $status = isset($redeemTicket[$a]['data'][$b]) ? $redeemTicket[$a]['data'][$b]['status'] : 'Active';

                    if ($status === 'Active') {
                        $packageWiseTickets[$a]->not_redeemed++;
                    }

                    if ($status === 'Inactive') {
                        $packageWiseTickets[$a]->redeemed++;
                    }
                }
            }
        }
        /* Tickets Calculation Start */

        foreach ($packageWiseTickets as $key => $redeemTicket) {
            $packageKey = array_search($redeemTicket->id, array_column($item->package_list, 'id'));

            $packageWiseRedeemedTickets += $redeemTicket->redeemed;
            $packageWiseNotRedeemedTickets += $redeemTicket->not_redeemed;
            $item->package_list[$packageKey]->not_redeemed += $redeemTicket->not_redeemed;
            $item->package_list[$packageKey]->redeemed += $redeemTicket->redeemed;
            $item->package_list[$packageKey]->total = $item->package_list[$packageKey]->not_redeemed + $item->package_list[$packageKey]->redeemed;

            if ($item->package_list[$packageKey]->total == $item->package_list[$packageKey]->redeemed) {
                $item->package_list[$packageKey]->class = 'px-[13px] py-[5px] mb-2 text-sm whitespace-nowrap bg-[#dc354520] rounded-[100px] border border-[#f5c2c7] font-normal inline-block';
                $item->package_list[$packageKey]->af_class = 'px-[13px] py-[5px] mb-2 text-sm whitespace-nowrap bg-[#dc354520] rounded-[100px] border border-[#f5c2c7] font-normal inline-block';
            } else {
                $item->package_list[$packageKey]->class = 'px-[13px] py-[5px] text-sm whitespace-nowrap bg-[#19875430] rounded-[100px] border border-[#198754] font-normal inline-block';
                $item->package_list[$packageKey]->af_class = 'px-[13px] py-[5px] text-sm whitespace-nowrap bg-[#19875430] rounded-[100px] border border-[#198754] font-normal inline-block';
            }
        }

        return [
            'packageWiseNotRedeemedTickets' => $packageWiseNotRedeemedTickets,
            'packageWiseRedeemedTickets' => $packageWiseRedeemedTickets
        ];
    }

    public static function processAgendaSession(
        $bookingId,
        $paymentStatus,
        $tourPackagesId,
        $slotId,
        $date,
    ): void {
        if (!empty($bookingId)) {
            // Retrieve session values or default to provided ones.
            $bookingId      = session('bookingId', $bookingId);
            $paymentStatus  = session('paymentStatus', $paymentStatus);
            $tourPackagesId = session('tourPackagesId', $tourPackagesId);
            $slotId         = session('slotId', $slotId);
            $date           = session('date', $date);

            // Remove specific session keys.
            session()->forget([
                'bookingId',
                'paymentStatus',
                'tourPackagesId',
                'slotId',
                'date',
            ]);
        }
    }
    /**
     * Retrieve active tour packages based on user type and department package.
     *
     * @param string $userType
     * @param mixed $departmentPackage
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getTourPackages(string $userType, mixed $departmentPackage)
    {
        return TourPackage::when($userType == 'Affiliate', function ($q) {
            return $q->whereHas('packageAffiliate', function ($q) {
                $q->where('affiliate_id', Auth::id());
            });
        })
            ->when($departmentPackage, function ($q) {
                return $q->whereHas('departments', function ($q) {
                    $q->where('department_id', Auth::user()->department_id);
                });
            })
            ->where('status', 'Active')
            ->where('show_in_agenda', Constants::STATUS_ONE)
            ->orderBy('name')
            ->get(['id', 'name']);
    }


    /**
     * Retrieve the bookings with all necessary filters for agenda page.
     *
     * @param string $date
     * @param mixed $bookingId
     * @param mixed $tourPackagesId
     * @param mixed $departmentPackage
     * @param mixed $slotId
     * @param mixed $paymentStatus
     * @param mixed $userType
     * @param mixed $affiliateTotalTourists
     * @param mixed $affiliateSeats
     *
     * @return \Illuminate\Support\Collection
     */
    public static function getBookings(
        string $date,
        $bookingId,
        $tourPackagesId,
        $departmentPackage,
        $slotId,
        $paymentStatus,
        $userType,
        $tenant
    ) {
        $affiliateSeats = '';
        $affiliateTotalTourists = '';

        $affiliateSeats = DB::raw('0 as afl_seats');
        if ($userType == 'Affiliate') {
            $affiliateSeats = DB::raw('IFNULL(ast.seat_limit,0) as afl_seats');
            $affiliateTotalTourists = 'AND tbm2.customer_id = ' . Auth::id();
        }

        // connection dynamically
        $db = $tenant ? DB::connection('master') : DB::connection();
        return $db->table('tour_bookings_master AS tbm')
            ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
            ->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
            ->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
            ->leftJoin('hotels as h', 'h.id', '=', 'tbm.hotel_id')
            ->where('tsd.date', $date)
            ->where('tbm.status', 'Booked')
            ->where('tp.show_in_agenda', Constants::STATUS_ONE)
            ->when($bookingId, function ($query) use ($bookingId) {
                $strippedId = bookingPrefix($bookingId, true);
                if (is_numeric($strippedId)) {
                    return $query->where('tbm.id', (int) $strippedId);
                } else {
                    return $query->where(function ($q) use ($bookingId) {
                        $q->where('tbm.name', 'LIKE', "%{$bookingId}%")
                          ->orWhere('tbm.email', 'LIKE', "%{$bookingId}%");
                    });
                }
            })
            ->when($tourPackagesId && $tourPackagesId !== '0', function ($query) use ($tourPackagesId) {
                return $query->where('tp.id', $tourPackagesId);
            })
            ->when($departmentPackage, function ($query) use ($departmentPackage) {
                return $query->whereIn('tp.id', $departmentPackage);
            })
            ->when($slotId, function ($query) use ($slotId) {
                if (agendaView() == 1) {
                    return $query->where('tst.id', $slotId);
                } else {
                    if ($slotId == 'No') {
                        return $query->where('tbm.hotel_id', 0);
                    } else {
                        return $query->where('tbm.hotel_id', $slotId);
                    }
                }
            })
            ->when($paymentStatus && $paymentStatus == 'Completed', function ($query) {
                return $query->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('transactions')
                        ->whereColumn('tour_booking_id', 'tbm.id')
                        ->where('status', 'Completed');
                });
            })
            ->when($paymentStatus && $paymentStatus == 'Failed', function ($query) use ($date, $bookingId, $userType) {
                return $query->whereNotIn('tbm.id', static function ($q) use ($date, $bookingId, $userType) {
                    return $q->select('tour_booking_id')->from('transactions')
                        ->join('tour_bookings_master AS tbm', 'tbm.id', '=', 'transactions.tour_booking_id')
                        ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
                        ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
                        ->where('tsd.date', $date)
                        ->when($bookingId, function ($query) use ($bookingId) {
                            return $query->where('tbm.id', $bookingId);
                        })
                        ->when($userType == 'Affiliate', function ($query) {
                            return $query->leftJoin('tour_slot_times_affiliate as tsta', function ($query) {
                                return $query->on('tsta.time_from', '=', 'tst.time_from')
                                    ->on('tsta.time_to', '=', 'tst.time_to')
                                    ->on('tsta.tour_package_id', '=', 'tp.id');
                            })
                                ->leftJoin('affiliate_slot_times as ast', 'ast.tour_slot_time_id', '=', 'tsta.id')
                                ->where('ast.affiliate_id', Auth::id())
                                ->where('tbm.customer_id', Auth::id());
                        })
                        ->groupBy('tour_booking_id')
                        ->having(DB::raw('count(`transactions`.`id`)'), '>', 0);
                });
            })
            ->when($userType == 'Affiliate', function ($query) {
                return $query->leftJoin('tour_slot_times_affiliate as tsta', function ($query) {
                    return $query->on('tsta.time_from', '=', 'tst.time_from')
                        ->on('tsta.time_to', '=', 'tst.time_to')
                        ->on('tsta.tour_package_id', '=', 'tp.id');
                })
                    ->leftJoin('affiliate_slot_times as ast', 'ast.tour_slot_time_id', '=', 'tsta.id')
                    ->where('ast.affiliate_id', Auth::id())
                    ->where('tbm.customer_id', Auth::id());
            })
            ->groupBy('tbm.id')
            ->orderBy(agendaView() == 1 ? 'tst.time_from' : 'tbm.hotel_id')
            ->orderBy('tp.name')
            ->orderBy('c.name', 'asc')
            ->get([
                'tbm.id AS booking_id',
                'tbm.cart_id',
                'tbm.viator_bookingRef AS tripadvisor_id',
                'tbm.get_your_guide_reference_booking AS get_your_guide_booking_id',
                'tbm.ota_reference_booking AS ota_booking_id',
                'tbm.tour_slot_id',
                'tbm.hotel_id',
                'tbm.tourists',
                'tbm.infants',
                'tbm.comments',
                'tbm.note',
                'tbm.total',
                'tbm.liability_waiver_signing',
                'tbm.created_by_user_type',
                'tbm.is_reserved_for_affiliate',
                'tbm.reference_booking',
                'tbm.is_group_booking',
                'tst.date',
                'tst.time_slot_seats as seats',
                'tst.time_from',
                'tst.time_to',
                'tp.id AS tour_package_id',
                'tp.name AS tour_package_name',
                't_o.stripe_destination_id',
                'tp.package_has_slots',
                'tp.package_has_waiver',
                'tp.package_has_permit',
                'tp.bundle_package',
                'tp.checkin as is_check_in',
                'tp.checkin_type',
                'tp.hours',
                'tp.has_contacts',
                'tbm.name AS customer_name',
                'c.name as affiliate_name',
                'tbm.email',
                'tbm.phone_number',
                'c.is_affiliate',
                'tbm.booking_total',
                'h.name AS hotel_name',
                'h.address AS hotel_address',
                DB::raw('(SELECT via FROM transactions WHERE tour_booking_id = tbm.id ORDER BY id ASC LIMIT 1) AS via'),
                'tbm.status as booking_status',
                DB::raw('(SELECT status FROM transactions WHERE tour_booking_id = tbm.id ORDER BY id DESC LIMIT 1) AS payment_status'),
                // Correlated subquery uses cart_id index; avoids full tbm scan from derived table JOIN.
                DB::raw('(SELECT COUNT(*) FROM tour_bookings_master WHERE cart_id = tbm.cart_id) AS cartBookingsCount'),
                DB::raw(
                    "IFNULL((SELECT SUM(amount)
                    FROM transactions AS t
                    WHERE t.tour_booking_id = tbm.id
                    AND t.status = 'Completed'
                    GROUP BY t.tour_booking_id), 0) AS paids"
                ),
                DB::raw("IFNULL((SELECT SUM(tbm.tourists)
                FROM tour_bookings_master AS tbm
                WHERE tbm.tour_slot_id = tst.id
                    AND tbm.status != 'Cancelled'
                GROUP BY tbm.tour_slot_id),0) AS booked_seats"),
                DB::raw("IFNULL((SELECT SUM(tbm2.tourists)
                FROM tour_bookings_master AS tbm2
                JOIN customers AS c ON c.id = tbm2.customer_id
                WHERE tbm2.tour_slot_id = tst.id
                    AND c.is_affiliate = 'Yes'
                    AND tbm2.status != 'Cancelled'
                    " . $affiliateTotalTourists . "
                GROUP BY tbm2.tour_slot_id, tbm2.tour_package_id), 0) AS afl_tourists"),
                DB::raw("(CASE WHEN tbm.viator_bookingRef IS NOT NULL THEN 1 ELSE 0 END) AS viator_bookingRef"),
                DB::raw("(CASE WHEN tbm.get_your_guide_reference_booking IS NOT NULL THEN 1 ELSE 0 END) AS get_your_guide_reference_booking"),
                DB::raw("(CASE WHEN tbm.ota_reference_booking IS NOT NULL THEN 1 ELSE 0 END) AS ota_reference_booking"),
                DB::raw(
                    "IFNULL((SELECT GROUP_CONCAT(
                    CONCAT(tpbr.tour_package_id, ':', tpbr.package_name)
                    ORDER BY tp.name ASC
                    SEPARATOR '|'
                )
                FROM tour_package_bundle_relations AS tpbr
                WHERE tbm.tour_package_id = tpbr.parent_id
                AND tpbr.deleted_by = 0), CONCAT(tp.id, ':', tp.name)) as packages"
                ),
                DB::raw("(
                    SELECT GROUP_CONCAT(
                        CONCAT(
                            guide_name, ' (', total_tourists, ')',
                            IF(station_name IS NOT NULL, CONCAT(' (', station_name, ')'), ''),
                            ' [#ID:', guide_id, ']'
                        )
                        SEPARATOR ', '
                    )
                    FROM (
                        SELECT
                            btg2.guide_id AS guide_id,
                            CONCAT(tos2.first_name, ' ', tos2.last_name) AS guide_name,
                            SUM(btg2.tourists) AS total_tourists,
                            MAX(COALESCE(bssn2.name, s2.name)) AS station_name
                        FROM booking_tour_guides btg2
                        JOIN tour_operator_staff tos2 ON tos2.id = btg2.guide_id
                        LEFT JOIN stations s2 ON s2.id = btg2.station_id
                        LEFT JOIN board_station_specific_name bssn2
                            ON bssn2.station_id = btg2.station_id
                           AND bssn2.date = tsd.date
                           AND bssn2.time_from = tst.time_from
                        WHERE btg2.booking_id = tbm.id
                        GROUP BY btg2.guide_id
                    ) x
                ) AS assigned_guides"),

                $affiliateSeats
            ]);
    }

    /**
     * Retrieve the bookings with all necessary filters for agenda in App.
     *
     * @param string $date
     * @param mixed $bookingId
     * @param mixed $tourPackagesId
     * @param mixed $departmentPackage
     * @param mixed $slotId
     * @param mixed $paymentStatus
     * @param mixed $userType
     * @param mixed $affiliateTotalTourists
     * @param mixed $affiliateSeats
     *
     * @return \Illuminate\Support\Collection
     */
    public static function getBookingsForApp(
        string $date,
        $bookingId,
        $tourPackagesId,
        $departmentPackage,
        $slotId,
        $paymentStatus,
        $userType
    ) {
        return DB::table('tour_bookings_master AS tbm')
            ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
            ->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
            ->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
            ->leftJoin('hotels as h', 'h.id', '=', 'tbm.hotel_id')
            ->leftJoin('transactions as t', 't.tour_booking_id', '=', 'tbm.id')
            ->where('tsd.date', $date)
            ->where('tbm.status', 'Booked')
            ->where('tp.show_in_agenda', Constants::STATUS_ONE)
            ->when($bookingId, fn($query) => $query->where('tbm.id', $bookingId))
            ->when($tourPackagesId && $tourPackagesId != 0, fn($query) => $query->where('tp.id', $tourPackagesId))
            ->when($departmentPackage, fn($query) => $query->whereIn('tp.id', $departmentPackage))
            ->when($slotId, fn($query) =>
                agendaView() == 1 ? $query->where('tst.id', $slotId) : 
                ($slotId == 'No' ? $query->where('tbm.hotel_id', 0) : $query->where('tbm.hotel_id', $slotId))
            )
            ->when($paymentStatus == 2, fn($query) => $query->where('t.status', 'Completed'))
            ->when($paymentStatus == 3, fn($query) => 
                $query->whereNotIn('tbm.id', fn($subQuery) => 
                    $subQuery->select('tour_booking_id')->from('transactions')
                        ->join('tour_bookings_master AS tbm', 'tbm.id', '=', 'transactions.tour_booking_id')
                        ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
                        ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
                        ->where('tsd.date', $date)
                        ->when($bookingId, fn($query) => $query->where('tbm.id', $bookingId))
                        ->when($userType == 'Affiliate', fn($query) => 
                            $query->leftJoin('tour_slot_times_affiliate as tsta', fn($join) =>
                                $join->on('tsta.time_from', '=', 'tst.time_from')
                                    ->on('tsta.time_to', '=', 'tst.time_to')
                                    ->on('tsta.tour_package_id', '=', 'tp.id')
                            )
                            ->leftJoin('affiliate_slot_times as ast', 'ast.tour_slot_time_id', '=', 'tsta.id')
                            ->where('ast.affiliate_id', Auth::id())
                            ->where('tbm.customer_id', Auth::id())
                        )
                        ->groupBy('tour_booking_id')
                        ->having(DB::raw('count(`transactions`.`id`)'), '>', 0)
                )
            )
            ->when($userType == 'Affiliate', fn($query) =>
                $query->leftJoin('tour_slot_times_affiliate as tsta', fn($join) =>
                    $join->on('tsta.time_from', '=', 'tst.time_from')
                        ->on('tsta.time_to', '=', 'tst.time_to')
                        ->on('tsta.tour_package_id', '=', 'tp.id')
                )
                ->leftJoin('affiliate_slot_times as ast', 'ast.tour_slot_time_id', '=', 'tsta.id')
                ->where('ast.affiliate_id', Auth::id())
                ->where('tbm.customer_id', Auth::id())
            )
            ->groupBy('tbm.id')
            ->orderBy(agendaView() == 1 ? 'tst.time_from' : 'tbm.hotel_id')
            ->orderBy('tp.name')
            ->orderBy('c.name', 'asc')
            ->get([
                'tbm.id AS booking_id',
                'tbm.cart_id',
                'tbm.viator_bookingRef AS tripadvisor_id',
                'tbm.get_your_guide_reference_booking AS get_your_guide_booking_id',
                'tbm.ota_reference_booking AS ota_booking_id',
                'tbm.tour_slot_id',
                'tbm.hotel_id',
                'tbm.tourists',
                'tbm.comments',
                'tbm.note',
                'tbm.liability_waiver_signing',
                'tbm.created_by_user_type',
                'tbm.is_reserved_for_affiliate',
                'tbm.reference_booking',
                'tbm.is_group_booking',
                'tst.date',
                'tst.time_slot_seats as seats',
                'tst.time_from',
                'tst.time_to',
                'tp.id AS tour_package_id',
                'tp.name AS tour_package_name',
                't_o.stripe_destination_id',
                'tp.package_has_slots',
                'tp.package_has_waiver',
                'tp.package_has_permit',
                'tp.bundle_package',
                'tp.checkin as is_check_in',
                'tp.checkin_type',
                'tp.hours',
                'tp.has_contacts',
                'tbm.name AS customer_name',
                'c.name as affiliate_name',
                'tbm.email',
                't.via',
                'tbm.phone_number',
                'c.is_affiliate',
                'h.name AS hotel_name',
                // Correlated subquery uses cart_id index; avoids full tbm scan from derived table JOIN.
                DB::raw('(SELECT COUNT(*) FROM tour_bookings_master WHERE cart_id = tbm.cart_id) AS cartBookingsCount'),
                'tbm.booking_total',
                DB::raw(
                    "IFNULL((SELECT SUM(amount)
                    FROM transactions AS t
                    WHERE t.tour_booking_id = tbm.id
                    AND t.status = 'Completed'
                    GROUP BY t.tour_booking_id), 0) AS paids"
                ),
                DB::raw("IFNULL((SELECT SUM(tbm.tourists)
                    FROM tour_bookings_master AS tbm
                    WHERE tbm.tour_slot_id = tst.id
                        AND tbm.status != 'Cancelled'
                    GROUP BY tbm.tour_slot_id),0) AS booked_seats"),
                DB::raw("(CASE WHEN tbm.viator_bookingRef IS NOT NULL THEN 1 ELSE 0 END) AS viator_bookingRef"),
                DB::raw("(CASE WHEN tbm.get_your_guide_reference_booking IS NOT NULL THEN 1 ELSE 0 END) AS get_your_guide_reference_booking"),
                DB::raw("(CASE WHEN tbm.ota_reference_booking IS NOT NULL THEN 1 ELSE 0 END) AS ota_reference_booking"),
                DB::raw(
                "IFNULL((SELECT GROUP_CONCAT(
                            CONCAT(tpbr.tour_package_id, ':', tpbr.package_name)
                            ORDER BY tp.name ASC
                            SEPARATOR '|'
                        )
                        FROM tour_package_bundle_relations AS tpbr
                        WHERE tbm.tour_package_id = tpbr.parent_id
                        AND tpbr.deleted_by = 0), CONCAT(tp.id, ':', tp.name)) as packages"
                ),
            ]);
    }

    public static function processAffiliateBookingInfo($dayBooking, $date)
    {
        $tourBooking = TourBooking::findOrFail($dayBooking->booking_id);
        $affiliate = Customer::findOrFail($tourBooking->customer_id);

        $bookingSlotTime = Carbon::parse("$date {$dayBooking->time_from}");
        $carbonInstance = Carbon::parse("$date {$dayBooking->time_from}");

        // Check if duration is greater than 0 and permission is granted
        if ($affiliate->duration > 0 && !checkPermission('booking-after-duration')) {
            $carbonInstance = ($affiliate->day_hours === 1)
                ? $carbonInstance->subDays($affiliate->duration)
                : $carbonInstance->subHours($affiliate->duration);

            if ($carbonInstance <= $bookingSlotTime && Carbon::now() >= $carbonInstance) {
                $dayBooking->affiliateFeature = 0;
            }
        }

        // Calculate available seats
        $tourists = (int) $dayBooking->booked_seats; // Total Tourists
        $seats = (int) $dayBooking->seats; // Total Seats
        $remainingSeats = max(0, $seats - $tourists); // Total Remaining Seats

        // Affiliate Seats Calculation
        $aflTourists = (int) $dayBooking->afl_tourists; // Total Affiliate Tourists
        $aflSeats = (int) $dayBooking->afl_seats; // Affiliate Total Seats
        $aflRemainingSeats = max(0, $aflSeats - $aflTourists); // Affiliate Total Remaining Seats

        if($aflRemainingSeats < $remainingSeats) {
            $remainingSeats = $aflRemainingSeats;
        }

        // Update dayBooking values
        $dayBooking->seats = $dayBooking->afl_seats;
        $dayBooking->aflSeats = $dayBooking->afl_seats;
        $dayBooking->aflTourists = $dayBooking->tourists;
        $dayBooking->aflTotalTourists = $dayBooking->afl_tourists;
        $dayBooking->aflRemainingSeats = $remainingSeats;

        return $dayBooking;
    }
}
