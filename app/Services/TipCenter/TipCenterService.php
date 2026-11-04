<?php

namespace App\Services\TipCenter;

use App\Constants\Constants;
use App\Traits\ActionButtons;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TipCenterService
{
    use ActionButtons;
    /**
     * Get assigned gratuity settings
     * 
     * @return mixed
     */
    public function fetchGratuitySettings($dt, $routePrefix): mixed
    {
        $selectFields = [
            'gs.id',
            'gs.gratuity_name',
            'gs.gratuity_type',
            'gs.distribution_type',
            'gs.pre_gratuity_enabled',
            'gs.post_gratuity_enabled',
            'gs.status',
            DB::raw("GROUP_CONCAT(DISTINCT tp.name SEPARATOR ', ') as assigned_packages"),
        ];

        $query = DB::table('gratuity_settings as gs')
            ->leftJoin('gratuity_package_assignments as gpa', 'gpa.gratuity_setting_id', '=', 'gs.id')
            ->leftJoin('tour_packages as tp', 'tp.id', '=', 'gpa.package_id')
            ->select($selectFields)
            ->groupBy('gs.id', 'gs.gratuity_name', 'gs.gratuity_type', 'gs.distribution_type', 'gs.status');


        return $dt->of($query)
            ->addIndexColumn()
            ->editColumn('pre_gratuity_enabled', function ($gratuitySetting) {
                if ($gratuitySetting->pre_gratuity_enabled) {
                    return '<span class="badge bg-info"> Pre </span>';
                } else if ($gratuitySetting->post_gratuity_enabled) {
                    return '<span class="badge bg-info">Post</span>';
                }
            })
            ->editColumn('gratuity_type', function ($gratuitySetting) {
                if ($gratuitySetting->gratuity_type == 'fixed_percentage') {
                    return '<span class="badge bg-primary"> Fixed Percentage </span>';
                } else if ($gratuitySetting->gratuity_type == 'fixed_amount') {
                    return '<span class="badge bg-secondary">Fixed Amount</span>';
                } else if ($gratuitySetting->gratuity_type == 'flexible') {
                    return '<span class="badge bg-info">Flexible</span>';
                }
            })
            ->editColumn('distribution_type', function ($gratuitySetting) {
                if ($gratuitySetting->distribution_type == 'equal') {
                    return '<span class="badge bg-primary"> Equal </span>';
                } else if ($gratuitySetting->distribution_type == 'custom') {
                    return '<span class="badge bg-info">Custom</span>';
                }
            })
            ->editColumn('assigned_packages', function ($gratuitySetting) {
                if (!empty($gratuitySetting->assigned_packages)) {
                    $packages = explode(',', $gratuitySetting->assigned_packages);
                    $badgeHtml = '';
                    foreach ($packages as $package) {
                        $badgeHtml .= '<span class="guide-badge badge bg-success">' . trim($package) . '</span>';
                    }
                    return $badgeHtml;
                } else {
                    return '<span class="badge bg-warning me-1">Not Assigned</span>';
                }
            })
            ->editColumn('status', function ($gratuitySetting) {
                if ($gratuitySetting->status == 'Active') {
                    return '<span class="badge bg-success">' . $gratuitySetting->status . '</span>';
                } else {
                    return '<span class="badge bg-danger">' . $gratuitySetting->status . '</span>';
                }
            })
            ->editColumn('action', function ($gratuitySetting) use ($routePrefix) {
                $actionButtons = self::getEditButton(route($routePrefix . '.tip-center.edit-gratuity-setting', ['id' => $gratuitySetting->id]));
                return $actionButtons;
            })
            ->rawColumns(['distribution_type', 'gratuity_type', 'assigned_packages', 'action', 'status', 'pre_gratuity_enabled'])
            ->make(true);
    }
    /**
     * Get assigned gratuity setting for the tour package
     * 
     * @return mixed
     */
    public static function getAssignedGratuitySetting($tourPackageId): mixed
    {
        return DB::table('gratuity_package_assignments as gpa')
            ->join('gratuity_settings as gs', 'gpa.gratuity_setting_id', '=', 'gs.id')
            ->where('gpa.package_id', $tourPackageId)
            ->where('gs.pre_gratuity_enabled', Constants::STATUS_ONE)
            ->select([
                'gpa.id',
                'gpa.gratuity_setting_id',
                'gpa.package_id',
                'gs.gratuity_name',
                'gs.gratuity_type',
                'gs.gratuity_rules',
                'gs.pre_gratuity_enabled',
                'gs.allow_pre_custom_gratuity',
                'gs.is_mandatory',
                'gs.service_provider_cut'
            ])
            ->first();
    }
    /**
     * Get assigned post gratuity setting for the tour package
     * 
     * @return mixed
     */
    public static function getAssignedPostGratuitySetting($tourPackageId): mixed
    {
        return DB::table('gratuity_package_assignments as gpa')
            ->join('gratuity_settings as gs', 'gpa.gratuity_setting_id', '=', 'gs.id')
            ->where('gpa.package_id', $tourPackageId)
            ->where('gs.post_gratuity_enabled', Constants::STATUS_ONE)
            ->select([
                'gpa.id',
                'gpa.gratuity_setting_id',
                'gpa.package_id',
                'gs.gratuity_name',
                'gs.gratuity_type',
                'gs.gratuity_rules',
                'gs.pre_gratuity_enabled',
                'gs.allow_pre_custom_gratuity',
                'gs.is_mandatory',
                'gs.service_provider_cut'
            ])
            ->first();
    }
    /**
     * Get all distributions for the given request parameters.
     *
     * @return \Illuminate\Support\Collection
     */
    public static function fetchDistributions($request)
    {
        $fromDate = Carbon::parse($request->fromDate)->format('Ymd');
        $toDate   = Carbon::parse($request->toDate)->format('Ymd');

        $tourPackageId     = $request->packageId ?? null;
        $guideId           = $request->guideId ?? null;
        $slotId            = $request->slotId ?? null;
        $searchTerm        = $request->searchTerm ?? null;
        $payrollScheduleId = $request->payrollScheduleId ?? null;

        // Common select fields
        $baseFields = [
            'tbm.tour_slot_id',
            'tst.date',
            'tst.time_from',
            'tst.time_to',
            DB::raw("CONCAT(tos.first_name, ' ', tos.last_name) as guide_name"),
            'tos.id as guide_id',
            'g.id as gratuity_id',
            'g.amount as total_gratuity',
            'tp.name as package_name',
            'tsd.date'
        ];

        if ($payrollScheduleId > 0) {
            $selectFields = array_merge($baseFields, [
                'gd.booking_id',
                'gd.amount as distribution_amount',
                'gd.status',
                'tbm.name as customer_name'
            ]);

            $query = self::buildDistributionQueryUsingDistributions($payrollScheduleId);
        } else {
            $selectFields = array_merge($baseFields, [
                'btg.booking_id',
                'btg.distribution_percentage',
                'btg.distribution_amount',
                'gd.status'
            ]);

            $query = self::buildDistributionQueryUsingTourGuides($fromDate, $toDate, $tourPackageId, $guideId, $slotId, $searchTerm);
        }

        $query->select($selectFields);

        $results = $query->get();

        if ($payrollScheduleId > 0) {
            $directGratuities = DB::table('gratuity_distributions as gd')
                ->join('tour_operator_staff as tos', 'tos.id', '=', 'gd.guide_id')
                ->join('gratuities as g', 'g.id', '=', 'gd.gratuity_id')
                ->where('gd.payroll_schedule_id', $payrollScheduleId)
                ->where('g.gratuity_type', 'direct')
                ->where('g.booking_id', null)
                ->groupBy('g.id', 'tos.id')
                ->get([
                    'g.id as gratuity_id',
                    'g.amount as total_gratuity',
                    DB::raw("CONCAT(tos.first_name, ' ', tos.last_name) as guide_name"),
                    'tos.id as guide_id',
                    'gd.amount as distribution_amount',
                    'gd.status',
                    DB::raw("DATE_FORMAT(g.created_at, '%Y-%m-%d') as date")
                ]);
        } else {
            $directGratuities = self::fetchDirectTips($fromDate, $toDate);
        }
        // Export vs normal view
        if ($request->boolean('export')) {

            $allResults = collect($results)->merge($directGratuities);

            return $allResults
                ->groupBy(fn($item) => $item->guide_id . ' - ' . $item->guide_name)
                ->map(function ($guideEntries) {
                    $guideInfo = $guideEntries->first();

                    return [
                        'guide_id' => $guideInfo->guide_id,
                        'guide_name' => $guideInfo->guide_name,
                        'total_payout' => $guideEntries->sum('distribution_amount'),
                        'bookings' => $guideEntries->map(fn($entry) => [
                            'booking_id' => $entry->booking_id ?? null, // ensure exists
                            'package_name' => $entry->package_name ?? null,
                            'customer_name' => $entry->customer_name ?? '',
                            'date' => $entry->date,
                            'time_from' => $entry->time_from ?? null,
                            'time_to' => $entry->time_to ?? null,
                            'total_gratuity' => $entry->total_gratuity,
                            'distribution_amount' => $entry->distribution_amount ?? 0,
                            'distribution_status' => $entry->status ?? '',
                        ])->values(),
                    ];
                })->values();
        }

        $slotResults = $results->groupBy(fn($item) => $item->time_from . ' - ' . $item->time_to)
            ->map(function ($slotEntries) {
                $slotInfo = $slotEntries->first();
                $bookings = $slotEntries->groupBy('booking_id')->map(function ($entries, $bookingId) {
                    $first = $entries->first();
                    return [
                        'booking_id'           => $bookingId,
                        'package_name'         => $first->package_name,
                        'date'                 => $first->date,
                        'total_gratuity'       => $first->total_gratuity,
                        'distribution_status'  => $first->status,
                        'guides'               => $entries->map(function ($entry) {
                            return [
                                'guide_id'              => $entry->guide_id,
                                'guide_name'            => $entry->guide_name,
                                'distribution_amount'   => $entry->distribution_amount ?? 0,
                                'expected_share'        => $entry->distribution_percentage ?? 0,
                            ];
                        })->values(),
                    ];
                });
                return [
                    'tour_slot_id' => Str::random(15),
                    'package_name' => $slotInfo->package_name,
                    'date'         => $slotInfo->date,
                    'time_from'    => $slotInfo->time_from,
                    'time_to'      => $slotInfo->time_to,
                    'bookings'     => $bookings->values(),
                ];
            })->values();


        $directSlot = $directGratuities->count() > 0
            ? collect([[
                'tour_slot_id' => 'direct_' . Str::random(10),
                'package_name' => '-',
                'date'         => null,
                'time_from'    => 'Direct Tips',
                'time_to'      => null,
                'bookings'     => $directGratuities->map(function ($entry) {
                    return [
                        'booking_id'           => '',
                        'gratuity_id'          => $entry->gratuity_id,
                        'package_name'         => '-',
                        'date'                 => $entry->date,
                        'total_gratuity'       => $entry->total_gratuity,
                        'distribution_status'  => $entry->status,
                        'guides'               => [[
                            'guide_id'                => $entry->guide_id,
                            'guide_name'              => $entry->guide_name,
                            'distribution_amount'     => $entry->total_gratuity ?? 0,
                            'expected_share'          => 0,
                        ]],
                    ];
                })->values(),
            ]])
            : collect([]);

        return $directSlot->merge($slotResults)->values();
    }

    private static function buildDistributionQueryUsingTourGuides($fromDate, $toDate, $tourPackageId, $guideId, $slotId, $searchTerm)
    {
        return DB::table('booking_tour_guides as btg')
            ->join('tour_bookings_master as tbm', 'tbm.id', '=', 'btg.booking_id')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
            ->leftJoin('gratuities as g', 'g.booking_id', '=', 'btg.booking_id')
            ->leftJoin('tour_operator_staff as tos', 'tos.id', '=', 'btg.guide_id')
            ->leftJoin('gratuity_distributions as gd', function ($join) {
                $join->on('gd.gratuity_id', '=', 'g.id')
                    ->on('gd.guide_id', '=', 'btg.guide_id');
            })
            ->whereBetween('tsd.date', [$fromDate, $toDate])
            ->where('tbm.status', 'Booked')
            ->when($guideId, fn($q) => $q->where('btg.guide_id', $guideId))
            ->when($tourPackageId && $tourPackageId !== '0', fn($q) => $q->where('tp.id', $tourPackageId))
            ->when($slotId, fn($q) => $q->where('tst.id', $slotId))
            ->when($searchTerm, fn($q) => $q->where('tbm.id', $searchTerm))
            ->whereNotNull('g.amount')
            ->groupBy('btg.booking_id', 'tos.id', 'g.id')
            ->orderBy('tst.time_from');
    }

    private static function buildDistributionQueryUsingDistributions($payrollScheduleId)
    {
        return DB::table('gratuity_distributions as gd')
            ->join('tour_bookings_master as tbm', 'tbm.id', '=', 'gd.booking_id')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
            ->leftJoin('gratuities as g', 'g.booking_id', '=', 'gd.booking_id')
            ->leftJoin('tour_operator_staff as tos', 'tos.id', '=', 'gd.guide_id')
            ->where('gd.payroll_schedule_id', $payrollScheduleId)
            ->where('tbm.status', 'Booked')
            ->groupBy('gd.booking_id', 'tos.id', 'g.id')
            ->orderBy('tst.time_from')
            ->orderBy('tp.name');
    }

    private static function fetchDirectTips($fromDate, $toDate)
    {
        return  DB::table('gratuities as g')
            ->leftJoin('tour_operator_staff as tos', 'tos.id', '=', 'g.guide_id')
            ->leftJoin('gratuity_distributions as gd', function ($join) {
                $join->on('gd.gratuity_id', '=', 'g.id')
                    ->on('gd.guide_id', '=', 'g.guide_id');
            })
            ->where(function ($query) use ($fromDate, $toDate) {
                $query->where(function ($q) use ($fromDate, $toDate) {
                    // gratuities linked with booking → filter by tour date
                    $q->whereNull('g.booking_id')
                        ->whereBetween(DB::raw('DATE(g.created_at)'), [$fromDate, $toDate]);
                });
            })
            ->where('g.gratuity_type', 'direct')
            ->where('g.booking_id', null)
            ->groupBy('g.id', 'tos.id')
            ->get([
                'g.id as gratuity_id',
                'g.amount as total_gratuity',
                DB::raw("CONCAT(tos.first_name, ' ', tos.last_name) as guide_name"),
                'tos.id as guide_id',
                'gd.amount as distribution_amount',
                'gd.status',
                DB::raw("DATE_FORMAT(g.created_at, '%Y-%m-%d') as date")
            ]);
    }


    /**
     * Get all bookings for the given request parameters.
     * 
     * @return mixed
     */

    public static function getGuideBookings($request)
    {
        $fromDate = Carbon::parse($request->fromDate)->format('Ymd');
        $toDate = Carbon::parse($request->toDate)->format('Ymd');
        $tourPackageId = $request->packageId ?? null;
        $guideId = $request->guideId ?? null;
        $slotId = $request->slotId ?? null;
        $searchTerm = $request->searchTerm ?? null;
        $userType = userType();
        $totalAssignedBookingsCount = 0;
        $totalUnAssignedBookingsCount = 0;
        $totalBookingsCount = 0;
        $selectFields = [
            'tbm.id AS booking_id',
            'tbm.tour_slot_id',
            'tbm.tourists',
            'tst.date',
            'tst.time_from',
            'tst.time_to',
            'tp.id AS tour_package_id',
            'tp.name AS tour_package_name',
            'tbm.name AS customer_name',
            't.via',
            DB::raw(
                "IFNULL((SELECT SUM(amount)
                FROM transactions AS t
                WHERE t.tour_booking_id = tbm.id
                AND t.status = 'Completed'
                GROUP BY t.tour_booking_id), 0) AS paids"
            ),
            'tbm.booking_total',
            DB::raw("GROUP_CONCAT(DISTINCT CONCAT(tos.first_name, ' ', tos.last_name, ' (', btg.tourists, ')') SEPARATOR ', ') as assigned_guides"), //' (', btg.tourists, ')'
            DB::raw(
                $userType != 'Admin'
                    ? "IFNULL(SUM(g.amount), 0) as tip_total"
                    : "0 as tip_total"
            ),
        ];
        $query = DB::table('tour_bookings_master AS tbm')
            ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
            ->leftJoin('booking_tour_guides as btg', 'btg.booking_id', '=', 'tbm.id')
            ->leftJoin('tour_operator_staff as tos', 'tos.id', '=', 'btg.guide_id')
            ->leftJoin('transactions as t', 't.tour_booking_id', '=', 'tbm.id')
            ->when($userType != 'Admin', function ($query) {
                // Join gratuities table only for guides
                $query->leftJoin('gratuities as g', 'g.booking_id', '=', 'tbm.id');
            })
            ->where('tbm.status', 'Booked')
            ->whereBetween('tsd.date', [$fromDate, $toDate])
            ->when($guideId, function ($query) use ($guideId) {
                return $query->where('btg.guide_id', $guideId);
            })
            ->when($tourPackageId && $tourPackageId !== '0', function ($query) use ($tourPackageId) {
                return $query->where('tp.id', $tourPackageId);
            })
            ->when($slotId, function ($query) use ($slotId) {
                return $query->where('tst.id', $slotId);
            })
            ->when($searchTerm, function ($query) use ($searchTerm) {
                return $query->where('tbm.id', $searchTerm);
            })
            ->groupBy('tbm.id')
            ->orderBy('tst.time_from')
            ->orderBy('tp.name')
            ->select($selectFields);
        $groupedBySlot = $query->get()->groupBy(function ($item) {
            return $item->time_from . ' - ' . $item->time_to;
        });

        $slotsBookings = $groupedBySlot->map(function ($slotEntries) use (&$totalAssignedBookingsCount, &$totalUnAssignedBookingsCount, &$totalBookingsCount) {
            $bookings = $slotEntries->map(function ($bookingEntry) use (&$totalAssignedBookingsCount, &$totalUnAssignedBookingsCount, &$totalBookingsCount) {
                if ($bookingEntry->assigned_guides != '') {
                    $totalAssignedBookingsCount = $totalAssignedBookingsCount + 1;
                } else {
                    $totalUnAssignedBookingsCount = $totalUnAssignedBookingsCount + 1;
                }
                $totalBookingsCount = $totalBookingsCount + 1;
                return [
                    'booking_id' => $bookingEntry->booking_id,
                    'tourists' => $bookingEntry->tourists,
                    'date' => $bookingEntry->date,
                    'customer_name' => $bookingEntry->customer_name,
                    'tour_package_name' => $bookingEntry->tour_package_name,
                    'assigned_guides' => $bookingEntry->assigned_guides,
                    'payment_method' => $bookingEntry->via,
                    'tip_total' => $bookingEntry->tip_total,
                    'paid' => (in_array($bookingEntry->via, ['Card', 'Cash', 'Invoiced', 'TripAdvisor', 'GetYourGuide']) && $bookingEntry->booking_total <= $bookingEntry->paids) ? 1 : 0
                ];
            })->values();
            $slotInfo = $slotEntries->first();
            $uniqId = Str::random(15);
            return [
                'tour_slot_id' => $uniqId,
                'package_name' => $slotInfo->tour_package_name,
                'time_from' => $slotInfo->time_from,
                'time_to' => $slotInfo->time_to,
                'bookings' => $bookings->values()
            ];
        })->values();

        $summary = [
            'totalAssignedBookingsCount' => $totalAssignedBookingsCount,
            'totalUnAssignedBookingsCount' => $totalUnAssignedBookingsCount,
            'totalBookingsCount' => $totalBookingsCount,
        ];
        return ['summary' => $summary, 'slotsBookings' => $slotsBookings];
    }
    /**
     * Insert gratuity setting
     * 
     * @return mixed
     */

    public static function insertGratuitySetting($inputData)
    {
        return
            DB::table('gratuity_settings')->insertGetId([
                'gratuity_name' => $inputData['gratuityName'],
                'gratuity_type' => $inputData['gratuityType'],
                'gratuity_rules' => (isset($inputData['gratuityAmounts'])) ? json_encode($inputData['gratuityAmounts']) : [],
                'pre_gratuity_enabled' => $inputData['preGratuity'],
                'post_gratuity_enabled' => $inputData['postGratuity'],
                'allow_pre_custom_gratuity' => $inputData['customGratuityPre'],
                'allow_post_custom_gratuity' => $inputData['customGratuityPost'],
                'min_gratuity' => $inputData['minGratuity'] ?? 0,
                'max_gratuity' => $inputData['maxGratuity'] ?? 0,
                'default_gratuity' => $inputData['defaultGratuity'] ?? 0,
                'is_mandatory' => $inputData['gratuityMandatory'],
                'service_provider_cut' => $inputData['serviceProviderCut'] ?? 0,
                'distribution_type' => $inputData['gratuityDistribution'],
                'created_by' => Auth::id(),
                'created_by_user_type' => userType(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
    }
    /**
     * Insert gratuity setting
     * 
     * @return void
     */

    public static function updateGratuitySetting($gratuitySettingId, $inputData)
    {
        DB::table('gratuity_settings')
            ->where('id', $gratuitySettingId)
            ->update([
                'gratuity_name' => $inputData['gratuityName'],
                'gratuity_type' => $inputData['gratuityType'],
                'gratuity_rules' => (isset($inputData['gratuityAmounts'])) ? json_encode($inputData['gratuityAmounts']) : [],
                'pre_gratuity_enabled' => $inputData['preGratuity'],
                'post_gratuity_enabled' => $inputData['postGratuity'],
                'allow_pre_custom_gratuity' => $inputData['customGratuityPre'],
                'allow_post_custom_gratuity' => $inputData['customGratuityPost'],
                'min_gratuity' => $inputData['minGratuity'] ?? 0,
                'max_gratuity' => $inputData['maxGratuity'] ?? 0,
                'default_gratuity' => $inputData['defaultGratuity'] ?? 0,
                'is_mandatory' => $inputData['gratuityMandatory'],
                'service_provider_cut' => $inputData['serviceProviderCut'] ?? 0,
                'distribution_type' => $inputData['gratuityDistribution'],
                'updated_by' => Auth::id(),
                'updated_by_user_type' => userType(),
                'updated_at' => Carbon::now(),
            ]);
    }

    /**
     * Fetch all guides
     * 
     * @return mixed
     */

    public static function fetchGuides($searchTerm = '')
    {

        $profilePhotoPath = ($searchTerm != '') ? DB::raw("CONCAT('" . Storage::disk('s3')->url('images/tour-operator/') . "', t_o.tenant_id, '/users/', tos.profile_photo_path) as profile_picture") : 'tos.profile_photo_path';
        $selectFields = [
            'tos.id',
            DB::raw("CONCAT(tos.first_name,' ',tos.last_name) as name"),
            'tos.status',
            'tos.email',
            'tos.capacity',
            'tos.phone_number',
            'tos.stripe_account_id',
            'tos.bank_account_number',
            'tos.bank_routing_number',
            'tos.bank_account_type',
            'tos.account_holder_name',
            DB::raw("COALESCE(SUM(btg.tourists), 0) as total_tourists"),
            $profilePhotoPath
        ];
        $guides = DB::table('tour_operator_staff as tos')
            ->join('tour_operators as t_o', 't_o.id', '=', 'tos.tour_operator_id')
            ->join('model_has_roles as mhr', 'mhr.model_id', '=', 'tos.id')
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->leftJoin('booking_tour_guides as btg', 'btg.guide_id', '=', 'tos.id')
            ->where('mhr.model_type', 'App\Models\Staff')
            ->where('r.name', 'tour-guide')
            ->when($searchTerm, function ($query, $searchTerm) {
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('tos.first_name', 'like', "%{$searchTerm}%")
                        ->orWhere('tos.last_name', 'like', "%{$searchTerm}%")
                        ->orWhere('tos.email', 'like', "%{$searchTerm}%");
                });
            })
            ->groupBy('tos.id')
            ->orderBy('tos.first_name')
            ->get($selectFields);
        return $guides;
    }
    /**
     * Fetch all bookings of the guide
     * 
     * @return mixed
     */

    public static function myBookings($guideId)
    {
        $selectFields = [
            'btg.booking_id',
            'g.id as gratuity_id',
            'g.amount as total_gratuity',
            'tp.name as package_name',
            'tsd.date',
            'tbm.tourists',
        ];
        $query = DB::table('booking_tour_guides as btg')
            ->join('tour_bookings_master as tbm', 'tbm.id', '=', 'btg.booking_id')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
            ->leftJoin('gratuities as g', 'g.booking_id', '=', 'btg.booking_id')
            ->where('btg.guide_id', $guideId);

        $query->select($selectFields);
        $myBookings = $query->get();
        return $myBookings;
    }
    /**
     * Fetch all tour packages
     * 
     * @return mixed
     */

    public static function fetchTourPackages()
    {
        $tourPackages = DB::table('tour_packages')
            ->select('id', 'name')
            ->where('status', 'Active')
            ->get();
        return $tourPackages;
    }
    /**
     * Get total tip amount by date range
     * 
     * @return mixed
     */

    public static function getTipAmountsByDateRange($fromDate, $toDate, $guideId = null)
    {
        return DB::table('gratuities as g')
            ->leftJoin('tour_bookings_master AS tbm', 'tbm.id', '=', 'g.booking_id')
            ->leftJoin('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->leftJoin('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
            ->when($guideId, function ($query) use ($guideId) {
                $query->join('booking_tour_guides as btg', 'btg.booking_id', '=', 'g.booking_id')
                    ->where('btg.guide_id', $guideId);
            })
            ->where(function ($query) use ($fromDate, $toDate) {
                $query->where(function ($q) use ($fromDate, $toDate) {
                    // gratuities linked with booking → filter by tour date
                    $q->whereNotNull('g.booking_id')
                        ->whereBetween('tsd.date', [$fromDate, $toDate]);
                })
                    ->orWhere(function ($q) use ($fromDate, $toDate) {
                        // gratuities direct (no booking) → filter by created_at
                        $q->whereNull('g.booking_id')
                            ->whereBetween(DB::raw('DATE(g.created_at)'), [$fromDate, $toDate]);
                    });
            })
            ->groupBy('g.gratuity_type')
            ->select(
                DB::raw('SUM(g.amount) as totalTipAmount'),
                DB::raw('CONCAT(UCASE(LEFT(g.gratuity_type, 1)), LCASE(SUBSTRING(g.gratuity_type, 2))) as tipType')
            )
            ->get()
            ->toArray() ?? [];
    }
    /**
     * Insert gratuity setting
     * 
     * @return void
     */

    public static function insertDistribution($bookingId = '', $gratuityId = '', $bookingGuide)
    {
        DB::table('gratuity_distributions')
            ->insert(
                [
                    'booking_id' => $bookingId,
                    'guide_id' => $bookingGuide->guide_id,
                    'gratuity_id' => $gratuityId,
                    'amount' => $bookingGuide->distribution_amount,
                    'status' => 'Ready',
                    'created_at' => now(),
                    'created_by' => Auth::id(),
                    'created_by_user_type' => userType()
                ]
            );
    }
    /**
     * Insert direct tip distributions
     * 
     * @return void
     */

    public static function insertDirectTipDistribution($gratuityId, $guideId, $gratuityAmount)
    {
        DB::table('gratuity_distributions')
            ->insert(
                [
                    'guide_id' => $guideId,
                    'gratuity_id' => $gratuityId,
                    'amount' => $gratuityAmount,
                    'status' => 'Ready',
                    'created_at' => now(),
                    'created_by' => Auth::id(),
                    'created_by_user_type' => userType()
                ]
            );
    }
    /**
     * Get all accepteddistributions for booking.
     * 
     * @return mixed
     */

    public static function getAcceptedDistributions($bookingId)
    {
        return DB::table('gratuity_distributions as gd')
            ->where('gd.booking_id', $bookingId)
            ->where('gd.status', 'Ready')
            ->get();
    }
}
