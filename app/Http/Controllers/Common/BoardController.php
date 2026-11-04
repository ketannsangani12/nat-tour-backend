<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Booking\BookingService;
use App\Services\Board\BoardService;
use App\Exceptions\Core\GeneralCoreException;
use Carbon\Carbon;
use App\Models\Station;
use App\Models\BookingTourGuide;
use App\Models\TourBooking;
use App\Models\TourPackage;
use App\Models\Staff;
use App\Services\Booking\HistoryService;
use Illuminate\Support\Facades\Auth;
use App\Services\Booking\TipService;
use App\Models\BoardStationPackage;
use App\Models\BoardStationSpecificPackage;
use App\Services\TourOperator\TourOperatorService;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\GuideSpecificCapacity;
use App\Events\Broadcast\Board\BoardUpdated;

class BoardController extends Controller
{
    /**
     * Board and station data render
     * 
     * @param Request $request
     */
    public function index(Request $request, BoardService $boardService)
    {
        try {
            $page_title = "Board";

            $date = $request->date
                ? Carbon::parse($request->date)->toDateString()
                : today()->toDateString();

            $data = $boardService->getBoardData($date);

            // timezone
            $tourOperator = TourOperatorService::getTourOperator();
            $tz = $tourOperator->timezone;
            $now = Carbon::now($tz);

            // normalize & sort slot list
            $sorted = collect($data['slots'])->sortBy(function ($s) {
                return str_replace('.', ':', trim($s->time_from));
            })->values();

            $currentSlotTime = null;
            $count = $sorted->count();

            // Convert current time to seconds
            $nowSeconds = $now->secondsSinceMidnight();

            // Convert all slot times to seconds
            $slotSeconds = [];

            foreach ($sorted as $s) {
                $t = str_replace('.', ':', trim($s->time_from));
                if (substr_count($t, ':') === 1) {
                    $t .= ':00';
                }
                $slotSeconds[] = Carbon::createFromFormat('H:i:s', $t, $tz)->secondsSinceMidnight();
            }

            // LOOP through slots
            for ($i = 0; $i < $count - 1; $i++) {
                $start = $slotSeconds[$i];
                $next  = $slotSeconds[$i + 1];

                // If current time is BETWEEN current AND next
                if ($nowSeconds >= $start && $nowSeconds < $next) {
                    // highlight the NEXT slot (your requirement)
                    $currentSlotTime = $sorted[$i + 1]->time_from;
                    break;
                }
            }

            // If current time is AFTER last slot → highlight last slot
            if (!$currentSlotTime && $count > 0) {
                $last = $slotSeconds[$count - 1];
                if ($nowSeconds >= $last) {
                    $currentSlotTime = $sorted->last()->time_from;
                }
            }

            // Fallback → first slot
            if (!$currentSlotTime && $count > 0) {
                $currentSlotTime = $sorted->first()->time_from;
            }

            $tourGuides = BookingService::getTourGuides();
            $boardStationPackage = BoardStationPackage::get();

             $date = $request->date
                ? Carbon::parse($request->date)
                : today();

            // Determine range from request, fallback to current month
            $fromDate = $request->from_date
                ? Carbon::parse($request->from_date)->startOfDay()
                : $date->copy()->startOfMonth();

            $toDate = $request->to_date
                ? Carbon::parse($request->to_date)->endOfDay()
                : $date->copy()->endOfMonth();

                // Current Phoenix timezone date (used for filtering)
            $today = now()->setTimezone($tz)->toDateString();
            
            if (!isset($request->date)) {
                // Fetch all guides’ assigned tourists for the provided range
                $assignmentsQuery = DB::table('booking_tour_guides as btg')
                    ->join('tour_bookings_master as tbm', 'tbm.id', '=', 'btg.booking_id')
                    ->select(
                        'btg.date',
                        DB::raw('SUM(COALESCE(btg.tourists,0) + COALESCE(btg.infants,0)) as total_assigned_tourists')
                    )
                    ->whereBetween('btg.date', [
                        $fromDate->toDateString(),
                        $toDate->toDateString()
                    ]);

                $assignments = $assignmentsQuery
                    ->groupBy('btg.date')
                    ->pluck('total_assigned_tourists', 'btg.date');
            }
            
            $stationList = clone $data['stations'];

            $stationList = $stationList->reject(function ($stationList) {
                return !empty($stationList->station_time_from) && !empty($stationList->station_date);
            });

            $data['stations_filtered'] = $stationList;

            // $allStations = $data['stations']; // your existing stations array/collection
            $allStations = $data['stations'] ?? [];

            $countsPerSlot = [];

            $selectedDate = ($date instanceof \Carbon\Carbon)
                ? $date->toDateString()
                : Carbon::parse($date)->toDateString();

            foreach ($allStations as $st) {

                $stationDate = $st->station_date ?? null;
                $stationTime = $st->station_time_from ?? null;
                
                $isDefault =
                    ($stationDate === null) ||
                    ($stationDate === '') ||
                    ($stationDate === '0000-00-00');

                if (!$isDefault) continue;

                if (!isset($countsPerSlot[$stationTime])) {
                    $countsPerSlot[$stationTime] = 0;
                }
                $countsPerSlot[$stationTime]++;
            }
            // default max
            $defaultMax = $countsPerSlot ? max($countsPerSlot) : 0;

            $countsPerSlot2 = [];

            foreach ($allStations as $st) {

                $stationDate = $st->station_date ?? null;
                $stationTime = $st->station_time_from ?? null;

                if ($stationTime === null) continue;

                if ($stationDate !== $selectedDate) continue;

                if (!isset($countsPerSlot2[$stationTime])) {
                    $countsPerSlot2[$stationTime] = 0;
                }
                $countsPerSlot2[$stationTime]++;
            }
            // selected-date max
            $selectedDateMax = $countsPerSlot2 ? max($countsPerSlot2) : 0;
            $maxStationsAcrossSlots = $defaultMax + $selectedDateMax;
            // Optionally keep normalizedStations the same shape you were using
            $normalizedStations = [];
            $maxStations = count($allStations);
            for ($i = 0; $i < $maxStations; $i++) {
                $normalizedStations[$i] = $allStations[$i] ?? null;
            }

            $data['normalizedStations'] = $normalizedStations;
            $data['maxStationsAcrossSlots'] = $maxStationsAcrossSlots;

            if ($request->ajax()) {
                $slotsHtml = view('partials.board.slots', [
                    'tourPackage'               => $data['slots'],
                    'normalizedStations'        => $data['normalizedStations'],
                    'maxStationsAcrossSlots'    => $data['maxStationsAcrossSlots'],
                    'currentSlotTime'           => $currentSlotTime,
                    'timezone'                  => $tz,
                ])->render();

                $stationListHtml = view('partials.board.station-list', [
                    'stationList' => $data['stations_filtered'],
                ])->render();

                $stationAddHtml = view('partials.board.station-add', [
                    'tourPackages'        => $data['tourPackages'],
                    'boardStationPackage' => $boardStationPackage,
                ])->render();

                $stationEditHtml = view('partials.board.station-edit', [
                    'tourPackages'        => $data['tourPackages'],
                    'boardStationPackage' => $boardStationPackage,
                ])->render();

                $guideListHtml = view('partials.board.guide-list', [
                    'tourGuides'       => $tourGuides,
                ])->render();

                return response()->json([
                    'success'          => true,
                    'slotsHtml'        => $slotsHtml,
                    'stationListHtml'  => $stationListHtml,
                    'stationAddHtml'   => $stationAddHtml,
                    'stationEditHtml'  => $stationEditHtml,
                    'guideListHtml'    => $guideListHtml,
                    'assignments' => $assignments ?? [],
                    'timezone'  =>$tz
                ]);
            }

            return view('partials.board.board', [
                'page_title'       => $page_title,
                'tourPackage'      => $data['slots'],
                'normalizedStations'         => $data['normalizedStations'],
                'maxStationsAcrossSlots' => $data['maxStationsAcrossSlots'],
                'stationList'         => $data['stations_filtered'],
                'tourPackages'     => $data['tourPackages'],
                'currentSlotTime'  => $currentSlotTime,
                'timezone'         => $tz,
                'tourGuides'       => $tourGuides,
                'boardChannel'     => (string) $tourOperator->id,
                'boardStationPackage' => $boardStationPackage,
                'assignments' => $assignments ?? [],
            ]);
        } catch (\Exception $exception) {
            \Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Store the station data
     * 
     * @param Request $request
     */
    public function storeStation(Request $request)
    {
        try {
            $routePrefix = routePrefix();

            // Merge and remove duplicate package IDs
            $tourPackages = array_unique(array_merge(
                $request->tour_packages ?? [],
                $request->general_packages ?? []
            ));

            // Remove duplicates + reindex keys
            $tourPackages = array_values(array_unique($tourPackages));

            // Ensure all values are strings (optional but safer for consistency)
            $tourPackages = array_map('strval', $tourPackages);

            // Save station
            $station = new Station;
            $station->name = $request->name;
            $station->status = $request->status;
            $station->tour_packages = json_encode($tourPackages);
            $station->station_time_from = $request->station_time_from;
            $station->station_date = $request->station_date ?? null;
            $station->save();

            // Fetch package names for response
            $packageNames = TourPackage::whereIn('id', $tourPackages)->pluck('name')->toArray();
            
            // Notify board: new station created for this slot
            BoardUpdated::dispatch($request->station_date, $request->station_time_from, $this->boardChannel());
            return response()->json([
                'success' => true,
                'station' => $station,
                'package_names' => $packageNames,
                'deleteUrl' => route($routePrefix . '.board.delete-station', ['id' => $station->id]),
                'updateUrl' => route($routePrefix . '.board.update-station', ['id' => $station->id]),
            ]);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Update the station data
     * 
     * @param Request $request
     * @param int $id
     */
    public function updateStation(Request $request, $id)
    {
        $routePrefix = routePrefix();

        $station = Station::findOrFail($id);
        $station->name = $request->name;
        $station->status = $request->status;

        // Avoid duplicate IDs
        // Merge and remove duplicate package IDs
        $tourPackages = array_unique(array_merge(
            $request->tour_packages ?? [],
            $request->general_packages ?? []
        ));
        // Remove duplicates + reindex keys
        $tourPackages = array_values(array_unique($tourPackages));

        // Ensure all values are strings (optional but safer for consistency)
        $tourPackages = array_map('strval', $tourPackages);

        $station->tour_packages = json_encode($tourPackages);
        $station->save();

        // Fetch package names for display
        $packageNames = TourPackage::whereIn('id', $tourPackages)->pluck('name')->toArray();

        return response()->json([
            'success' => true,
            'station' => $station,
            'package_names' => $packageNames,
            'updateUrl' => route($routePrefix . '.board.update-station', ['id' => $station->id]),
            'deleteUrl' => route($routePrefix . '.board.delete-station', ['id' => $station->id]),
        ]);
    }

    /**
     * Delete the station data
     * 
     * @param int $id
     */
    public function deleteStation($id)
    {
        try {
            $station = Station::findOrFail($id);
            $station->delete();

            return response()->json([
                'success' => true,
                'message' => 'Station deleted successfully'
            ]);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Guide management: Render the guide, booking list and assign guide and booking on station section
     * 
     * @param Request $request
     */

    public function guideManagement(Request $request)
    {
        try {
            $bookingArray = [];
            $absentBookingArray = [];
            $userType = userType();
            $authId = Auth::id();

            // Get stations
            $stations = Station::where(function ($query) use ($request) {
                $query->where(function ($q) {
                    $q->whereNull('station_date')
                      ->orWhere('station_date', '0000-00-00');
                })
                ->where(function ($q) {
                    $q->whereNull('station_time_from')
                      ->orWhere('station_time_from', '');
                })
                ->orWhere(function ($subQuery) use ($request) {
                    $subQuery->where('station_date', $request->date)
                             ->where('station_time_from', $request->time_from);
                });
            })->get();

            // Get bookings with assigned tourist counts
            $bookings = DB::table('tour_bookings_master as tbm')
                ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
                ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
                ->leftJoin('booking_tour_guides as btg', function ($join) use ($request) {
                    $join->on('btg.booking_id', '=', 'tbm.id')
                        ->where('btg.time_from', $request->time_from)
                        ->where('btg.date', $request->date);
                })
                ->where('tst.time_from', $request->time_from)
                ->where('tst.date', $request->date)
                ->where('tp.include_in_operation_board', 1)
                ->where('tbm.status', '!=' , 'Cancelled')
                ->select(
                    'tbm.id',
                    'tbm.tourists as total_tourists',
                    'tbm.infants',
                    DB::raw('COALESCE(SUM(btg.tourists),0) as assigned_tourists'),
                    DB::raw('COALESCE(SUM(btg.infants),0) as assigned_infants')
                )
                ->groupBy('tbm.id', 'tbm.tourists')
                ->get();

            foreach ($bookings as $booking) {
                // Check-in logic
                $checkInsResult = BoardService::checkInStatus($booking);
                $booking->checkInsCount = $checkInsResult->checkInsCount ?? ['1' => 0, '2' => 0, 'A' => 0];

                $absentCount   = $booking->checkInsCount['A'] ?? 0;
                $adjustedTotal = max(0, $booking->total_tourists - $absentCount);
                $assigned      = $booking->assigned_tourists ?? 0;
                $pending       = max(0, $adjustedTotal - $assigned);

                /*INFANTS CHECK-IN LOGIC*/
                $checkInsInfantsResult = BoardService::checkInInfantsStatus($booking);
                $booking->checkInsInfantsCount = $checkInsInfantsResult->checkInsInfantsCount ?? ['1' => 0, '2' => 0, 'A' => 0];

                $absentInfants   = $booking->checkInsInfantsCount['A'] ?? 0;
                $adjustedInfants = max(0, ($booking->infants ?? 0) - $absentInfants);
                $assignedInfants = $booking->assigned_infants ?? 0;
                $pendingInfants  = max(0, $adjustedInfants - $assignedInfants);

                // Fetch common booking info
                $info = DB::table('tour_bookings_master as tbm')
                    ->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
                    ->select('tbm.*', 'tp.name as tour_package')
                    ->where('tbm.id', $booking->id)
                    ->first();

                $info->total_tourists    = $adjustedTotal;
                $info->assigned_tourists = $assigned;
                $info->pending_tourists  = $pending;
                $info->checkInsCount     = $booking->checkInsCount;
                
                $info->infants           = $adjustedInfants;
                $info->assigned_infants  = $assignedInfants;
                $info->pending_infants   = $pendingInfants;
                $info->checkInsInfantsCount = $booking->checkInsInfantsCount;

                // If all are absent
                if ($absentCount == $booking->total_tourists) {
                    $info->total_tourists = $booking->total_tourists;
                    $absentBookingArray[] = $info;
                }
                // If some are still pending
                elseif ($assigned < $adjustedTotal) {
                    $bookingArray[] = $info;
                }
            }
            $pendingTourists = 0;
            $pendingInfants = 0;
            $bookingArray = collect($bookingArray)
                ->sortByDesc('total_tourists')
                ->map(function ($row) use (&$pendingTourists, &$pendingInfants) {
                    // Get check-in result from BoardService
                    $checkInsResult = BoardService::checkInStatus($row);
                    // Ensure default structure
                    $row->checkInsCount = $checkInsResult->checkInsCount ?? ['1' => 0, '2' => 0, 'A' => 0];
                    // Adjust tourists by removing Absent count
                    $absentCount = $row->checkInsCount['A'] ?? 0;
                    $row->tourists = max(0, $row->tourists - $absentCount);
                     // Accumulate totals
                    $pendingTourists += $row->tourists - $row->assigned_tourists;
                    $pendingInfants  += $row->infants - $row->assigned_infants ?? 0;
                    return $row;
                })
                ->values()
                ->toArray();

            // Finally, get available guides
            $guides = BoardService::getAvailableGuides($request);
            // Not available guides 
            $notAvailableGuides = BoardService::getNotAvailableGuides($request);
            
            // Guide assignments for current slot
            $bookingGuidesQuery = DB::table('booking_tour_guides')
                ->where('time_from', $request->time_from)
                ->where('date', $request->date);


            $bookingGuides = $bookingGuidesQuery->get(['guide_id', 'booking_id', 'tourists']);
            $busyGuideIds  = $bookingGuides->pluck('guide_id')->unique()->toArray();

            // Map guide_id => [booking_id => assigned_tourists]
            $guideBookingMap = [];
            foreach ($bookingGuides as $entry) {
                if ($entry->booking_id) {
                    $guideBookingMap[$entry->guide_id][$entry->booking_id] = $entry->tourists;
                }
            }

            $allBookingIds = collect($guideBookingMap)->flatten()->unique()->toArray();

            $bookingTourists = DB::table('tour_bookings_master')
                ->whereIn('id', $allBookingIds)
                ->pluck('tourists', 'id')
                ->toArray();

            $guides = $guides->map(function ($guide) use ($busyGuideIds, $guideBookingMap, $bookingTourists) {
                $guide->busy = in_array($guide->id, $busyGuideIds);
                $guide->tourists_assigned = 0;
                $guide->tourists_total = 0;
                $guide->total_assigned_bookings = 0;

                if (isset($guideBookingMap[$guide->id])) {
                    foreach ($guideBookingMap[$guide->id] as $bookingId => $assigned) {
                        $guide->tourists_assigned += max(0, $assigned ?? 0);
                        $guide->tourists_total += $bookingTourists[$bookingId] ?? 0;
                    }
                    $guide->total_assigned_bookings = count($guideBookingMap[$guide->id]);
                }

                return $guide;
            })->sortBy([
                ['busy', 'asc'],
                ['total_assigned_bookings', 'asc'],
            ])->values();

            // Build stations with assignments
            $stationsWithAssignments = $stations->map(function ($station) use ($userType, $request) {

                // Station name override
                $overrideStationName = DB::table('board_station_specific_name')
                    ->where('station_id', $station->id)
                    ->where('date', $request->date)
                    ->where('time_from', $request->time_from)
                    ->value('name');

                if ($overrideStationName) {
                    $station->name = $overrideStationName;
                }
                $assignedGuidesQuery = DB::table('booking_tour_guides as btg')
                    ->join('tour_operator_staff as tos', 'tos.id', '=', 'btg.guide_id')

                    // JOIN guide specific capacity (override)
                    ->leftJoin('guide_specific_capacity as gsc', function ($join) use ($station, $request) {
                        $join->on('gsc.guide_id', '=', 'btg.guide_id')
                             ->on('gsc.time_from', '=', 'btg.time_from')
                             ->where('gsc.date', '=', $request->date);
                    })
                    ->where('tos.status', 'Active')
                    ->where('btg.station_id', $station->id)
                    ->where('btg.time_from', $request->time_from)
                    ->where('btg.date', $request->date);

                $assignedGuides = $assignedGuidesQuery->get([
                    'tos.id',
                    DB::raw("CONCAT(tos.first_name, ' ', tos.last_name) AS guideName"),
                    'btg.booking_id',

                    // effective capacity
                    DB::raw('COALESCE(gsc.capacity, tos.capacity) AS capacity'),

                    'btg.tourists as assigned_tourists',
                    'btg.infants',
                ]);

                // Assigned bookings at this station
                $assignedBookingsQuery = DB::table('booking_tour_guides as btg')
                    ->join('tour_bookings_master as tbm', 'tbm.id', '=', 'btg.booking_id')
                    ->where('btg.station_id', $station->id)
                    ->where('btg.time_from', $request->time_from)
                    ->where('btg.date', $request->date);

                if ($userType === 'Tour Operator') {
                    $user = Staff::find(Auth::id());
                    if ($user->can('guide-board')) {
                        $assignedBookingsQuery->whereIn('btg.guide_id', $assignedGuides->pluck('id'));
                    }
                }

                $assignedBookings = $assignedBookingsQuery
                    ->select(
                        'btg.booking_id',
                        'btg.guide_id',
                        'btg.station_id',
                        'btg.tourists as assigned_tourists',
                        'tbm.tourists as total_tourists'
                    )
                    ->get()
                    ->map(function ($row) {
                        if ($row->booking_id) {
                            $booking = DB::table('tour_bookings_master AS tbm')
                                ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
                                ->join('booking_tour_guides AS btg', 'btg.booking_id', '=', 'tbm.id')
                                ->where('tbm.id', '=', $row->booking_id)
                                ->where('btg.tourists', $row->assigned_tourists)
                                ->where('btg.guide_id', $row->guide_id)
                                ->first([
                                    'tbm.id',
                                    'tbm.name',
                                    'tbm.tourists',
                                    'tp.name as tour_package',
                                    'tp.id as tour_package_id',
                                    'btg.check_in_status',
                                    'btg.booking_checked',
                                    'btg.infants',
                                ]);
                                
                            if ($booking) {
                                $checkInsResult = BoardService::checkInStatus($booking);
                                $booking->checkInsCount = $checkInsResult->checkInsCount ?? ['1' => 0, '2' => 0, 'A' => 0];

                                $booking->assignedGuide     = $row->guide_id;
                                $booking->assignedTourists  = $row->assigned_tourists;
                                $booking->total_tourists    = $row->total_tourists;
                                $booking->pending_tourists  = $row->total_tourists - $row->assigned_tourists;
                            }

                            return $booking;
                        }
                    })
                    ->filter()
                    ->sortByDesc('btg.booking_id')
                    ->values();

                // Tour packages — specific override takes precedence over station default
                $defaultPackageIds = json_decode($station->tour_packages, true) ?? [];
                $overridePackages = DB::table('board_station_specific_packages')
                    ->where('station_id', $station->id)
                    ->where('date', $request->date)
                    ->where('time_from', $request->time_from)
                    ->value('packages');
                $packageIds = $overridePackages
                    ? (json_decode($overridePackages, true) ?? $defaultPackageIds)
                    : $defaultPackageIds;
                $packages = DB::table('tour_packages')
                    ->whereIn('id', $packageIds)
                    ->get(['id', 'name']);

                $station->guides = $assignedGuides;
                $station->bookings = $assignedBookings;
                $station->packages = $packages;

                return $station;
            });

            $totalAssignedTourists = $stationsWithAssignments->sum(function ($station) {
                return collect($station->guides)->sum('assigned_tourists');
            });

            $totalGuideCapacity = $stationsWithAssignments
                ->flatMap(function ($station) {
                    return $station->guides;
                })
                ->unique('id') // ensure each guide counted once
                ->sum('capacity');

            // tour guide
            if ($userType === 'Tour Operator') {
                $user = Staff::find(Auth::id());
                if ($user->can('guide-board')) {
                    $stationsWithAssignments = $stationsWithAssignments->filter(fn($s) => $s->guides->isNotEmpty())->values();
                }
            }

            return response()->json([
                'stations' => $stationsWithAssignments,
                'guides'   => $guides,
                'bookings' => $bookingArray,
                'absent_bookings' => $absentBookingArray,
                'stationOneGuide' => true,
                'notAvailableGuides' => $notAvailableGuides,
                'totalAssignedTourists' => $totalAssignedTourists,
                'totalGuideCapacity' => $totalGuideCapacity,
                'pendingTourists' => $pendingTourists,
                'pendingInfants' => $pendingInfants,
            ]);

        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Assign guide and booking to the station using drag and drop
     * 
     * @param Request $request
     */
    public function assignGuideAndBooking(Request $request)
    {
        try {
            $stationId   = $request->station_id;
            $guideId     = $request->tour_guide_id;
            $timeFrom    = $request->time_from;
            $date        = $request->date;
            $bookingId   = $request->tour_booking_id ?? null;

            $assignFromOtherSide = BookingTourGuide::where('guide_id', $guideId)
                ->where('time_from', $timeFrom)
                ->where('date', $date)
                ->first();  

            if ($assignFromOtherSide && $assignFromOtherSide->station_id == null) {
                $tourBooking = TourBooking::find($assignFromOtherSide->booking_id);                        
                BoardService::blockNextTimeSlot($tourBooking->tour_package_id, $date, $timeFrom, $guideId, $stationId, $assignFromOtherSide->booking_id);

                $assignFromOtherSide->station_id = $stationId;
                $assignFromOtherSide->save();
            } 

            $existingAssignment = DB::table('booking_tour_guides')
                ->where('station_id', $stationId)
                ->where('guide_id', $guideId)
                ->where('time_from', $timeFrom)
                ->where('date', $date)
                ->first();

            if (is_null($bookingId)) {
                // Just assigning a guide without booking
                if ($existingAssignment && !is_null($existingAssignment->booking_id)) {
                    // Already has a booking, nothing more to do
                    return response()->json(['status' => 'success']);
                }

                // Reassign from other stations
                $otherAssignments = DB::table('booking_tour_guides')
                    ->where('guide_id', $guideId)
                    ->where('time_from', $timeFrom)
                    ->where('date', $date)
                    ->where('station_id', '!=', $stationId)
                    ->get();

                if ($otherAssignments->isNotEmpty()) {
                    foreach ($otherAssignments as $assignment) {
                        DB::table('booking_tour_guides')
                            ->where('id', $assignment->id)
                            ->update(['station_id' => $stationId]);
                    }

                    // unassign guide booking on corrent station 
                    BookingTourGuide::where('guide_id', 0)
                        ->where('time_from', $timeFrom)
                        ->where('date', $date)
                        ->where('station_id', '=', $stationId)
                        ->delete();

                } elseif (!$existingAssignment) {
                    // No assignment found, create one
                    $withOutGuide = DB::table('booking_tour_guides')
                                    ->where('station_id', $stationId)
                                    ->where('time_from', $timeFrom)
                                    ->where('date', $date)
                                    ->count();

                    if ($withOutGuide > 0) {

                        BookingTourGuide::where('station_id', $stationId)
                                    ->where('time_from', $timeFrom)
                                    ->where('date', $date)
                                    ->update(['guide_id' => $guideId]);

                        $getBookingGuide = DB::table('booking_tour_guides')
                                    ->where('station_id', $stationId)
                                    ->where('time_from', $timeFrom)
                                    ->where('date', $date)
                                    ->first();

                        $tourBooking = TourBooking::find($getBookingGuide->booking_id);                        
                        if ($tourBooking) {
                            BoardService::blockNextTimeSlot($tourBooking->tour_package_id, $date, $timeFrom, $guideId, $stationId, $getBookingGuide->booking_id); 
                        }              

                    } else {

                        DB::table('booking_tour_guides')->Insert(
                            [
                            'station_id' => $stationId,
                            'guide_id'   => $guideId,
                            'time_from'  => $timeFrom,
                            'date'       => $date,
                            'tourists'   => 0,
                            'created_at' => now()
                        ]);
                    }               
                    
                }
            } else {
                // Booking is provided → can be multiple guides per booking
                $tourBooking = TourBooking::find($bookingId);
                BoardService::blockNextTimeSlot($tourBooking->tour_package_id, $date, $timeFrom, $guideId, $stationId, $bookingId);
                if ($existingAssignment && $existingAssignment->booking_id && $existingAssignment->booking_id != $bookingId) {
                    // treat as new assignment
                    $tourBooking = TourBooking::find($bookingId);
                    $checkInsResult = BoardService::checkInStatus($tourBooking);
                    $checkInsCount = $checkInsResult->checkInsCount 
                        ?? ['1' => 0, '2' => 0, 'A' => 0];

                    $absentCount = $checkInsCount['A'] ?? 0;
                    $guide = DB::table('tour_operator_staff as tos')
                        ->leftJoin('guide_specific_capacity as gsc', function ($join) use ($guideId, $date, $timeFrom) {
                            $join->on('gsc.guide_id', '=', 'tos.id')
                                 ->where('gsc.date', '=', $date)
                                 ->where('gsc.time_from', '=', $timeFrom);
                        })
                        ->where('tos.id', $guideId)
                        ->where('tos.status', 'Active')
                        ->select(
                            'tos.*',
                            DB::raw('COALESCE(gsc.capacity, tos.capacity) as capacity')
                        )
                        ->firstOrFail();

                    // Guide capacity for this slot
                    $guideCapacity = $guide->capacity ?? $tourBooking->tourists;

                    // Map guide load (tourists already assigned)
                    $guideLoad = DB::table('booking_tour_guides as btg')
                        ->select('btg.guide_id', DB::raw('SUM(btg.tourists) as total'))
                        ->where('btg.time_from', $timeFrom)
                        ->where('btg.date', $date)
                        ->groupBy('btg.guide_id')
                        ->pluck('total', 'btg.guide_id')
                        ->toArray();

                    $alreadyAssignedToGuide = $guideLoad[$guideId] ?? 0;
                    $availableCapacity      = max($guideCapacity - $alreadyAssignedToGuide, 0);

                    // Calculate booking's pending tourists (not already assigned to other guides)
                    $assignedTouristsForBooking = DB::table('booking_tour_guides')
                        ->where('booking_id', $bookingId)
                        ->sum('tourists');
                    $pendingTouristsForBooking = max($tourBooking->tourists - $assignedTouristsForBooking - $absentCount, 0);

                    if ($availableCapacity <= 0 || $pendingTouristsForBooking <= 0) {
                        return response()->json([
                            'status'  => 'error',
                            'message' => 'Guide has no available capacity or booking has no pending tourists.'
                        ], 400);
                    }

                    // Total infants for booking
                    $checkInsInfantsResult = BoardService::checkInInfantsStatus($tourBooking);
                    $tourBooking->checkInsInfantsCount = $checkInsInfantsResult->checkInsInfantsCount ?? ['1' => 0, '2' => 0, 'A' => 0];
                    $absentInfants = $tourBooking->checkInsInfantsCount['A'] ?? 0;
                    $totalInfants = max(0, ($tourBooking->infants ?? 0) - $absentInfants);
                    // Already assigned infants (any guide)
                    $assignedInfantsForBooking = DB::table('booking_tour_guides')
                        ->where('booking_id', $bookingId)
                        ->sum('infants');

                    // Remaining infants
                    $pendingInfantsForBooking = max(
                        $totalInfants - $assignedInfantsForBooking,
                        0
                    );

                    // Assign only the minimum of available guide capacity and pending booking tourists
                    $toAssign = min($availableCapacity, $pendingTouristsForBooking);
                    $toAssignInfants  = $pendingInfantsForBooking;

                    DB::table('booking_tour_guides')->updateOrInsert(
                        [
                            'booking_id' => $bookingId,
                            'guide_id'   => $guideId,
                            'station_id' => $stationId,
                            'time_from'  => $timeFrom,
                            'date'       => $date,
                        ],
                        [
                            'tourists'   => $toAssign,
                            'infants'    => $toAssignInfants,
                            'updated_at' => now(),
                        ]
                    );

                    $guideName = "$guide->first_name $guide->last_name";
                    $note = "Tour guide $guideName has been successfully assigned to the booking with {$toAssign} tourists.";
                    HistoryService::storeNoteInHistory($note, $bookingId);
                    // Update distribution amounts after saving tour guide
                    TipService::updateDistributionAmounts($bookingId);

                } else {

                    // Update or create booking assignment for this guide/station/slot with capacity logic
                    $tourBooking = TourBooking::find($bookingId);
                    $checkInsResult = BoardService::checkInStatus($tourBooking);
                    $checkInsCount = $checkInsResult->checkInsCount 
                        ?? ['1' => 0, '2' => 0, 'A' => 0];

                    $absentCount = $checkInsCount['A'] ?? 0;
                    $guide = DB::table('tour_operator_staff as tos')
                        ->leftJoin('guide_specific_capacity as gsc', function ($join) use ($guideId, $date, $timeFrom) {
                            $join->on('gsc.guide_id', '=', 'tos.id')
                                 ->where('gsc.date', '=', $date)
                                 ->where('gsc.time_from', '=', $timeFrom);
                        })
                        ->where('tos.id', $guideId)
                        ->where('tos.status', 'Active')
                        ->select(
                            'tos.*',
                            DB::raw('COALESCE(gsc.capacity, tos.capacity) as capacity')
                        )
                        ->firstOrFail();

                    $guideCapacity = $guide->capacity ?? $tourBooking->tourists;

                    $guideLoad = DB::table('booking_tour_guides as btg')
                        ->select('btg.guide_id', DB::raw('SUM(btg.tourists) as total'))
                        ->where('btg.time_from', $timeFrom)
                        ->where('btg.date', $date)
                        ->groupBy('btg.guide_id')
                        ->pluck('total', 'btg.guide_id')
                        ->toArray();

                    $alreadyAssignedToGuide = $guideLoad[$guideId] ?? 0;
                    $availableCapacity      = max($guideCapacity - $alreadyAssignedToGuide, 0);

                    $assignedTouristsForBooking = DB::table('booking_tour_guides')
                        ->where('booking_id', $bookingId)
                        ->sum('tourists');

                    $pendingTouristsForBooking = max($tourBooking->tourists - $assignedTouristsForBooking - $absentCount, 0);

                    if ($availableCapacity <= 0 || $pendingTouristsForBooking <= 0) {
                        return response()->json([
                            'status'  => 'error',
                            'message' => 'Guide has no available capacity or booking has no pending tourists.'
                        ], 400);
                    }

                    // Total infants for booking
                    $checkInsInfantsResult = BoardService::checkInInfantsStatus($tourBooking);
                    $tourBooking->checkInsInfantsCount = $checkInsInfantsResult->checkInsInfantsCount ?? ['1' => 0, '2' => 0, 'A' => 0];
                    $absentInfants = $tourBooking->checkInsInfantsCount['A'] ?? 0;
                    $totalInfants = max(0, ($tourBooking->infants ?? 0) - $absentInfants);

                    // Already assigned infants (any guide)
                    $assignedInfantsForBooking = DB::table('booking_tour_guides')
                        ->where('booking_id', $bookingId)
                        ->sum('infants');

                    // Remaining infants
                    $pendingInfantsForBooking = max(
                        $totalInfants - $assignedInfantsForBooking,
                        0
                    );

                    // If user passed $tourists, respect it but cap within allowed range
                    $requestedTourists = $pendingTouristsForBooking;
                    $toAssign = min($requestedTourists, $availableCapacity, $pendingTouristsForBooking);
                    $toAssignInfants  = $pendingInfantsForBooking;

                    DB::table('booking_tour_guides')->updateOrInsert(
                        [
                            'station_id' => $stationId,
                            'guide_id'   => $guideId,
                            'time_from'  => $timeFrom,
                            'date'       => $date,
                        ],
                        [
                            'booking_id' => $bookingId,
                            'tourists'   => $toAssign,
                            'infants'    => $toAssignInfants,
                            'updated_at' => now()
                        ]
                    );

                    $guideName = "$guide->first_name $guide->last_name";
                    $note = "Tour guide $guideName assignment updated with {$toAssign} tourists.";
                    HistoryService::storeNoteInHistory($note, $bookingId);
                    // Update distribution amounts after saving tour guide
                    TipService::updateDistributionAmounts($bookingId);
                }

            }

            // Notify board: guide assigned to booking
            BoardUpdated::dispatch($date, $timeFrom, $this->boardChannel());
            return response()->json(['status' => 'success']);

        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Unassign booking from the station
     * 
     * @param Request $request
     */
    public function unAssignBooking(Request $request)
    {
        try {

            $stationId = $request->station_id;
            $guideId   = $request->tour_guide_id;
            $timeFrom  = $request->time_from;
            $date      = $request->date;
            $bookingId = $request->tour_booking_id;

            $guideAssignment = BookingTourGuide::where('station_id', $stationId)
                    ->where('guide_id', $guideId)
                    ->where('time_from', $timeFrom)
                    ->where('date', $date)
                    ->get();

            $existingAssignment = BookingTourGuide::where('station_id', $stationId)
                    ->where('guide_id', $guideId)
                    ->where('time_from', $timeFrom)
                    ->where('date', $date)
                    ->where('booking_id', $bookingId)
                    ->first();
            
            // History update
            $guide = Staff::findOrFail($guideId);
            $guideName = $guide->first_name.' ' .$guide->last_name;
            $note = "Tour guide $guideName has been successfully removed from the booking.";
            HistoryService::storeNoteInHistory($note, $bookingId);
            
            if ($existingAssignment) {
                if (count($guideAssignment) > 1 ) {
                     $existingAssignment->delete();

                 } else {
                    $existingAssignment->booking_id = null;
                    $existingAssignment->tourists = 0;
                    $existingAssignment->save();

                    BoardService::deleteBlockNextTimeSlot($date, $guideId,$timeFrom, $stationId, $existingAssignment->booking_id);
                 }
               
            }

            // Notify board: booking unassigned from guide
            BoardUpdated::dispatch($date, $timeFrom, $this->boardChannel());
            return response()->json(['success' => true]);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Unassign guide from the station
     * 
     * @param Request $request
     */
    public function unAssignGuide(Request $request)
    {
        try {

            $stationId = $request->station_id;
            $guideId   = $request->tour_guide_id;
            $timeFrom  = $request->time_from;
            $date      = $request->date;

            $existingAssignment = BookingTourGuide::where('station_id', $stationId)
                    ->where('guide_id', $guideId)
                    ->where('time_from', $timeFrom)
                    ->where('date', $date)
                    ->get();       
            
            // History update
            $guide = Staff::findOrFail($guideId);
            $guideName = "$guide->first_name $guide->last_name";
            $note = "Tour guide $guideName has been successfully removed from the booking.";

            if ( count($existingAssignment) > 0 ) {
                foreach ($existingAssignment as $value) {

                    if ($value->booking_id != null) {
                        HistoryService::storeNoteInHistory($note, $value->booking_id);
                    } else {
                        $value->delete();
                    }
                    
                }
                BookingTourGuide::where('station_id', $stationId)
                    ->where('guide_id', $guideId)
                    ->where('time_from', $timeFrom)
                    ->where('date', $date)
                    ->update(['guide_id' => null]);
            }
            BoardService::deleteBlockNextTimeSlot($date, $guideId,$timeFrom, $stationId, null);
            // Notify board: guide unassigned from station
            BoardUpdated::dispatch($date, $timeFrom, $this->boardChannel());
            return response()->json(['success' => true]);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Station order update
     * 
     * @param Request $request
     */
    public function updateStationOrder(Request $request)
    {
        try {
            $order = $request->order; // array of station IDs in new order

            foreach ($order as $index => $id) {
                Station::where('id', $id)->update(['order_by' => $index + 1]);
            }

            return response()->json(['success' => true]);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        } 
    }

    /**
     * Auto assign
     * 
     * @param Request $request
     */
    public function autoAssign(Request $request)
    {
        $timeFrom = $request->time_from;
        $date     = $request->date;

        // Get bookings that still have pending tourists for this slot
        $bookings = DB::table('tour_bookings_master as tbm')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->leftJoin('booking_tour_guides as btg', function ($join) use ($timeFrom, $date) {
                $join->on('btg.booking_id', '=', 'tbm.id')
                     ->where('btg.time_from', $timeFrom)
                     ->where('btg.date', $date);
            })
            ->where('tst.time_from', $timeFrom)
            ->whereDate('tst.date', $date)
            ->where('tp.include_in_operation_board', 1)
            ->where('tbm.status', '!=' , 'Cancelled')
            ->groupBy('tbm.id', 'tbm.tourists', 'tbm.tour_package_id')
            ->select([
                'tbm.id',
                'tbm.tour_package_id',
                DB::raw('tbm.tourists AS total_tourists'),
                DB::raw('GREATEST(tbm.tourists - COALESCE(SUM(btg.tourists), 0), 0) AS remaining_tourists'),
                DB::raw('GREATEST(tbm.infants - COALESCE(SUM(btg.infants), 0), 0) AS remaining_infants'),
            ])
            ->having('remaining_tourists', '>', 0)
            ->get();

        if ($bookings->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No pending tourists to auto-assign',
            ]);
        }

        // Stations with allowed packages
        $stations = Station::where(function ($query) use ($request, $date, $timeFrom) {
            $query->where(function ($q) {
                    $q->whereNull('station_date')
                      ->orWhere('station_date', '0000-00-00');
                })
                ->where(function ($q) {
                    $q->whereNull('station_time_from')
                      ->orWhere('station_time_from', '');
                })
                ->orWhere(function ($subQuery) use ($date, $timeFrom) {
                    $subQuery->where('station_date', $date)
                             ->where('station_time_from', $timeFrom);
                });
        })->get();

        // Guides assigned in this slot
        $guides = DB::table('booking_tour_guides as btg')
            ->join('tour_operator_staff as tos', 'tos.id', '=', 'btg.guide_id')
            // JOIN guide specific capacity (override)
            ->leftJoin('guide_specific_capacity as gsc', function ($join) use ($date, $timeFrom) {
                $join->on('gsc.guide_id', '=', 'btg.guide_id')
                     ->on('gsc.time_from', '=', 'btg.time_from')
                     ->where('gsc.date', '=', $date);
            })
            ->where('btg.time_from', $timeFrom)
            ->where('btg.date', $date)
            ->where('btg.check_in_status', '!=', 'Completed')
            ->where('tos.status', 'Active')
            ->get([
                'btg.guide_id',
                'btg.station_id',
                // effective capacity
                DB::raw('COALESCE(gsc.capacity, tos.capacity) AS capacity'),
            ]);

        // Map guide load (tourists already assigned)
        $guideLoad = DB::table('booking_tour_guides as btg')
            ->select('btg.guide_id', DB::raw('SUM(btg.tourists) as total'))
            ->where('btg.time_from', $timeFrom)
            ->where('btg.date', $date)
            ->groupBy('btg.guide_id')
            ->pluck('total', 'btg.guide_id')
            ->toArray();

        // Pre-load specific package overrides for this date+time keyed by station_id
        $specificPackageOverrides = DB::table('board_station_specific_packages')
            ->where('date', $date)
            ->where('time_from', $timeFrom)
            ->pluck('packages', 'station_id')
            ->map(fn ($json) => array_map('intval', json_decode($json, true) ?? []))
            ->toArray();

        foreach ($bookings as $booking) {
            $bookingPackage  = (int) $booking->tour_package_id;

            $checkInsResult = BoardService::checkInStatus($booking);
            $booking->checkInsCount = $checkInsResult->checkInsCount ?? ['1' => 0, '2' => 0, 'A' => 0];

            // Subtract absent tourists (A)
            $absentCount = $booking->checkInsCount['A'] ?? 0;
            $remainingTourists  = (int) $booking->remaining_tourists - $absentCount;

            $checkInsInfantsResult = BoardService::checkInInfantsStatus($booking);
            $booking->checkInsInfantsCount = $checkInsInfantsResult->checkInsInfantsCount ?? ['1' => 0, '2' => 0, 'A' => 0];
            $absentInfants = $booking->checkInsInfantsCount['A'] ?? 0;
            $remainingInfants = max(0, ($booking->remaining_infants ?? 0) - $absentInfants);

            // Skip this booking if nothing left to assign
            if ($remainingTourists <= 0 && $remainingInfants <= 0) {
                continue;
            }

            // Find stations that support this package
            foreach ($stations as $station) {
                // Use specific override if exists, otherwise fall back to station default
                $stationPackages = array_map('intval',
                    $specificPackageOverrides[$station->id]
                        ?? json_decode($station->tour_packages, true)
                        ?? []
                );

                if (!in_array($bookingPackage, $stationPackages)) {
                    continue;
                }

                // Guides assigned at this station
                $stationGuides = $guides->where('station_id', $station->id);

                foreach ($stationGuides as $guide) {
                    $currentLoad = $guideLoad[$guide->guide_id] ?? 0;
                    $remainingCapacity = $guide->capacity - $currentLoad;

                    // Only assign if guide can take the full booking
                    if ($remainingCapacity >= $remainingTourists) {
                        BoardService::blockNextTimeSlot($booking->tour_package_id, $date, $timeFrom, $guide->guide_id, $station->id, $booking->id);
                        $existing = DB::table('booking_tour_guides')
                            ->where('booking_id', $booking->id)
                            ->where('guide_id', $guide->guide_id)
                            ->where('station_id', $station->id)
                            ->where('time_from', $timeFrom)
                            ->where('date', $date)
                            ->first();

                        if ($existing) {
                            DB::table('booking_tour_guides')
                                ->where('id', $existing->id)
                                ->update([
                                    'tourists'   => $existing->tourists + $remainingTourists,
                                    'infants'    => ($existing->infants ?? 0) + $remainingInfants,
                                    'updated_at' => now(),
                                ]);
                        } else {
                            $existingWithBooking = BookingTourGuide::where('guide_id', $guide->guide_id)
                                ->where('station_id', $station->id)
                                ->where('time_from', $timeFrom)
                                ->where('date', $date)
                                ->first();

                            if ($existingWithBooking && $existingWithBooking->booking_id == null) {
                                $existingWithBooking->booking_id = $booking->id;
                                $existingWithBooking->tourists   = $remainingTourists;
                                $existingWithBooking->infants    = $remainingInfants;
                                $existingWithBooking->save();
                            } else {

                                DB::table('booking_tour_guides')->updateOrInsert(
                                    [
                                        'booking_id' => $booking->id,
                                        'guide_id'   => $guide->guide_id,
                                        'station_id' => $station->id,
                                        'time_from'  => $timeFrom,
                                        'date'       => $date,
                                    ],
                                    [
                                        'tourists'                => $remainingTourists,
                                        'infants'                 => $remainingInfants,
                                        'distribution_percentage' => 0,
                                        'distribution_amount'     => 0,
                                        'check_in_status'         => 'Pending',
                                        'updated_at'              => now(),
                                    ]
                                );
                            } 
                            
                        }

                        TipService::updateDistributionAmounts($booking->id);
                        // Update guide load and stop processing this booking
                        $guideLoad[$guide->guide_id] = $currentLoad + $remainingTourists;
                        $remainingTourists = 0;
                        $remainingInfants  = 0;

                        // History update
                        $guide = Staff::findOrFail($guide->guide_id);
                        $guideName = $guide->first_name.' ' .$guide->last_name;
                        $note = "Tour guide $guideName has been successfully assigned to the booking.";
                        HistoryService::storeNoteInHistory($note, $booking->id);

                        break 2; // booking fully assigned
                    }
                }
            }
        }

        // Notify board: auto-assign guides completed
        BoardUpdated::dispatch($date, $timeFrom, $this->boardChannel());
        return response()->json(['success' => true, 'message' => 'Auto-assign completed']);
    }

    /**
     * Bulk assign
     * 
     * @param Request $request
     */
    public function bulkAssign(Request $request)
    {
        $stationId  = $request->station_id;
        $guideId    = $request->tour_guide_id;
        $timeFrom   = $request->time_from;
        $date       = $request->date;
        $bookingIds = $request->tour_booking_ids;

        if (empty($bookingIds)) {
            return response()->json(['success' => false, 'message' => 'No bookings provided.']);
        }

        // Get bookings with tourists + remaining tourists for this slot
        $bookings = DB::table('tour_bookings_master as tbm')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->leftJoin('booking_tour_guides as btg', function ($join) use ($timeFrom, $date) {
                $join->on('btg.booking_id', '=', 'tbm.id')
                     ->where('btg.time_from', $timeFrom)
                     ->where('btg.date', $date);
            })
            ->whereIn('tbm.id', $bookingIds)
            ->where('tp.include_in_operation_board', 1)
            ->where('tbm.status', '!=' , 'Cancelled')
            ->groupBy('tbm.id', 'tbm.tourists')
            ->select([
                'tbm.id',
                'tbm.tour_package_id',
                DB::raw('tbm.tourists AS total_tourists'),
                DB::raw('tbm.infants AS total_infants'),
                DB::raw('GREATEST(tbm.tourists - COALESCE(SUM(btg.tourists), 0), 0) AS remaining_tourists'),
                DB::raw('GREATEST(tbm.infants - COALESCE(SUM(btg.infants), 0), 0) AS remaining_infants'),
            ])
            ->having('remaining_tourists', '>', 0)
            ->get();

        if ($bookings->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No remaining tourists to assign.']);
        }

        $assignedCount = 0;
        $guide = DB::table('tour_operator_staff as tos')
            ->leftJoin('guide_specific_capacity as gsc', function ($join) use ($guideId, $date, $timeFrom) {
                $join->on('gsc.guide_id', '=', 'tos.id')
                     ->where('gsc.date', '=', $date)
                     ->where('gsc.time_from', '=', $timeFrom);
            })
            ->where('tos.id', $guideId)
            ->where('tos.status', 'Active')
            ->select('tos.*', DB::raw('COALESCE(gsc.capacity, tos.capacity) as capacity'))
            ->firstOrFail();

        $guideName       = $guide->first_name . ' ' . $guide->last_name;
        $guideCapacity   = (int) ($guide->capacity ?? 0);
        $currentGuideLoad = (int) DB::table('booking_tour_guides')
            ->where('guide_id', $guideId)
            ->where('time_from', $timeFrom)
            ->where('date', $date)
            ->sum('tourists');

        foreach ($bookings as $booking) {

            // Stop assigning once the guide is at full capacity
            $availableCapacity = $guideCapacity > 0
                ? max(0, $guideCapacity - $currentGuideLoad)
                : PHP_INT_MAX;

            if ($availableCapacity <= 0) {
                break;
            }

            BoardService::blockNextTimeSlot($booking->tour_package_id, $date, $timeFrom, $guideId, $stationId, $booking->id);
            $checkInsResult = BoardService::checkInStatus($booking);
            $booking->checkInsCount = $checkInsResult->checkInsCount ?? ['1' => 0, '2' => 0, 'A' => 0];

            $absentCount    = $booking->checkInsCount['A'] ?? 0;
            $assignTourists = min(
                max(0, (int) $booking->remaining_tourists - $absentCount),
                $availableCapacity
            );

            $checkInsInfantsResult = BoardService::checkInInfantsStatus($booking);
            $booking->checkInsInfantsCount = $checkInsInfantsResult->checkInsInfantsCount ?? ['1' => 0, '2' => 0, 'A' => 0];

            $absentInfants = $booking->checkInsInfantsCount['A'] ?? 0;
            $assignInfants = max(0, ($booking->remaining_infants ?? 0) - $absentInfants);

            if ($assignTourists > 0 || $assignInfants > 0) {
                DB::table('booking_tour_guides')->updateOrInsert(
                    [
                        'booking_id' => $booking->id,
                        'guide_id'   => $guideId,
                        'station_id' => $stationId,
                        'time_from'  => $timeFrom,
                        'date'       => $date,
                    ],
                    [
                        'tourists'                => $assignTourists,
                        'infants'                 => $assignInfants,
                        'distribution_percentage' => 0,
                        'distribution_amount'     => 0,
                        'updated_at'              => now(),
                    ]
                );

                $currentGuideLoad += $assignTourists;
                $assignedCount++;

                $note = "Tour guide $guideName has been successfully assigned to the booking.";
                HistoryService::storeNoteInHistory($note, $booking->id);

                TipService::updateDistributionAmounts($booking->id);
            }
        }

        // Notify board: bulk guide assignment completed
        BoardUpdated::dispatch($date, $timeFrom, $this->boardChannel());
        return response()->json([
            'success' => true,
            'message' => 'Bookings assigned successfully.',
            'assigned_count' => $assignedCount
        ]);
    }

    /**
     * Split booking details
     * 
     * @param Request $request
     */
    public function splitBookingDetails(Request $request)
    {
        $timeFrom   = $request->time_from;
        $date       = $request->date;
        $bookingId  = $request->tour_booking_id;

        // Get assigned guides for this booking
        $bookingTourGuides = BookingTourGuide::where('booking_id', $bookingId)
            ->where('time_from', $timeFrom)
            ->where('date', $date)
            ->get();

        // Get total tourists for this booking
        $totalTourists = DB::table('tour_bookings_master')
            ->where('id', $bookingId)
            ->value('tourists');

        // Attach guide capacity to each assigned guide
        $guideCapacity = DB::table('tour_operator_staff as tos')
            ->leftJoin('guide_specific_capacity as gsc', function ($join) use ($guide, $date, $timeFrom) {
                $join->on('gsc.guide_id', '=', 'tos.id')
                     ->where('gsc.date', '=', $date)
                     ->where('gsc.time_from', '=', $timeFrom);
            })
            ->where('tos.id', $guide->tour_guide_id)
            ->where('tos.status', 'Active')
            ->value(DB::raw('COALESCE(gsc.capacity, tos.capacity)'));

        return response()->json([
            'totalTourists' => $totalTourists,
            'guideCapacity' => $guideCapacity,
            'bookingTourGuides' => $bookingTourGuides
        ]);
    }

    /**
     * Bulk check-in status update
     * 
     * @param Request $request
     */

    public function bulkCheckIn(Request $request)
    {
        try {
            $timeFrom   = $request->time_from;
            $date       = $request->date;
            $stationId  = $request->station_id;
            $guideId    = $request->guide_id;
            $action     = $request->action; // ready | second | absent

            // Map actions to DB values + history labels
            $statusMap = [
                'ready'  => ['val' => '1', 'label' => '1st Check In'],
                'second' => ['val' => '2', 'label' => '2nd Check In'],
                'absent' => ['val' => 'A', 'label' => 'Absent'],
            ];

            if (!isset($statusMap[$action])) {
                return response()->json(['success' => false, 'message' => 'Invalid action']);
            }

            $statusVal   = $statusMap[$action]['val'];
            $statusLabel = $statusMap[$action]['label'];

            $station = DB::table('stations as s')
                ->leftJoin('board_station_specific_name as bssn', function ($join) use ($date, $timeFrom) {
                    $join->on('bssn.station_id', '=', 's.id')
                         ->on('bssn.time_from', '=', DB::raw("'" . $timeFrom . "'"))
                         ->where('bssn.date', '=', $date);
                })

                ->where('s.id', $stationId)

                ->select(
                    DB::raw("COALESCE(bssn.name, s.name) as station_name")
                )
            ->first();

            $stationName = $station->station_name ?? $stationId;

            // Fetch guide assignments for this station/guide/slot
            $btgRows = DB::table('booking_tour_guides')
                ->where('time_from', $timeFrom)
                ->where('date', $date)
                ->where('station_id', $stationId)
                ->where('guide_id', $guideId)
                ->get();

            if ($btgRows->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No bookings found']);
            }

            $historyLogged = [];
            $updatedCount  = 0; // total individuals updated

            foreach ($btgRows as $btg) {
                $bookingId     = $btg->booking_id;
                $assignedCount = (int) $btg->tourists; // only update assigned tourists

                // multiple transactions (age groups)
                $txns = DB::table('tour_bookings_transact')
                    ->where('tour_booking_id', $bookingId)
                    ->where('is_infant_rate', 0)
                    ->get();

                if ($txns->isEmpty()) {
                    continue;
                }

                foreach ($txns as $txn) {
                    if ($assignedCount <= 0) break; // nothing left to allocate for this BTG

                    $checkIns = json_decode($txn->check_in_status, true) ?? [];
                    if (empty($checkIns)) {
                        continue;
                    }

                    $individualsUpdatedInTxn = 0;

                    if ($action === 'second') {
                        // PRIORITIZE entries that are already status '1' -> set them to '2'
                        $eligibleIdx = [];
                        foreach ($checkIns as $i => $ci) {
                            if (isset($ci['status']) && $ci['status'] === '1') {
                                $eligibleIdx[] = $i;
                            }
                        }

                        $toUpdate = min(count($eligibleIdx), $assignedCount);
                        for ($k = 0; $k < $toUpdate; $k++) {
                            $idx = $eligibleIdx[$k];
                            $checkIns[$idx]['status'] = '2';
                            $checkIns[$idx]['dateTime'] = now()->format('Y-m-d H:i:s');
                            $individualsUpdatedInTxn++;
                        }
                    } elseif ($action === 'ready') {
                        // Update un-checked entries to '1' (skip those already 1,2 or A)
                        $eligibleIdx = [];
                        foreach ($checkIns as $i => $ci) {
                            $status = $ci['status'] ?? '';
                            if ($status !== 'A' && $status !== '1' && $status !== '2') {
                                $eligibleIdx[] = $i;
                            }
                        }

                        $toUpdate = min(count($eligibleIdx), $assignedCount);
                        for ($k = 0; $k < $toUpdate; $k++) {
                            $idx = $eligibleIdx[$k];
                            $checkIns[$idx]['status'] = '1';
                            $checkIns[$idx]['dateTime'] = now()->format('Y-m-d H:i:s');
                            $individualsUpdatedInTxn++;
                        }
                    } elseif ($action === 'absent') {
                        // Mark non-absent entries as absent up to assignedCount
                        $eligibleIdx = [];
                        foreach ($checkIns as $i => $ci) {
                            $status = $ci['status'] ?? '';
                            if ($status !== 'A') {
                                $eligibleIdx[] = $i;
                            }
                        }

                        $toUpdate = min(count($eligibleIdx), $assignedCount);
                        for ($k = 0; $k < $toUpdate; $k++) {
                            $idx = $eligibleIdx[$k];
                            $checkIns[$idx]['status'] = 'A';
                            $checkIns[$idx]['dateTime'] = now()->format('Y-m-d H:i:s');
                            $individualsUpdatedInTxn++;
                        }
                    }

                    // if we updated any individual statuses in this txn, persist
                    if ($individualsUpdatedInTxn > 0) {
                        DB::table('tour_bookings_transact')
                            ->where('id', $txn->id)
                            ->update([
                                'check_in_status' => json_encode($checkIns),
                                'updated_at'      => now(),
                            ]);

                        $assignedCount -= $individualsUpdatedInTxn; // reduce left count
                        $updatedCount += $individualsUpdatedInTxn;
                    }
                }

                /* -------------------------------------------------
                 | INFANT FIX — ALL NON-ABSENT → 2nd CHECK IN
                 -------------------------------------------------*/
                if ($action === 'second') {

                    $infantTxns = DB::table('tour_bookings_transact')
                        ->where('tour_booking_id', $bookingId)
                        ->where('is_infant_rate', 1)
                        ->get();

                    foreach ($infantTxns as $txn) {

                        $checkIns = json_decode($txn->check_in_status, true) ?? [];
                        if (empty($checkIns)) continue;

                        $updated = false;

                        foreach ($checkIns as $i => $ci) {
                            $status = $ci['status'] ?? '';

                            // Skip absent completely
                            if ($status === 'A') {
                                continue;
                            }

                            // Promote anything not already 2
                            if ($status !== '2') {
                                $checkIns[$i]['status']   = '2';
                                $checkIns[$i]['dateTime'] = now()->format('Y-m-d H:i:s');
                                $updated = true;
                            }
                        }

                        if ($updated) {
                            DB::table('tour_bookings_transact')
                                ->where('id', $txn->id)
                                ->update([
                                    'check_in_status' => json_encode($checkIns),
                                    'updated_at'      => now(),
                                ]);
                        }
                    }
                }

                // update BTG row (keeping original behavior)
                DB::table('booking_tour_guides')
                    ->where('id', $btg->id)
                    ->update([
                        'check_in_status' => 'Completed',
                        'check_in_count'  => (int) $btg->tourists,
                        'updated_at'      => now(),
                    ]);

                // log history once per booking
                if (!in_array($bookingId, $historyLogged)) {
                    $detail = [
                        'status'               => $statusLabel,
                        'who'                  => "Station: {$stationName}",
                        'created_by_with_name' => BookingService::createdUserInfo(),
                    ];
                    HistoryService::storeCheckInDataInHistory(
                        $bookingId,
                        $detail,
                        now()->format('Y-m-d H:i:s')
                    );
                    $historyLogged[] = $bookingId;
                }
            }

            // Notify board: bulk check-in completed
            BoardUpdated::dispatch($date, $timeFrom, $this->boardChannel());
            return response()->json([
                'success' => true,
                'message' => $statusLabel . ' completed',
                'updated' => $updatedCount,
            ]);

        } catch (Exception $exception) {
            Log::error($exception);
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    /**
     * Execute to get assigned guides for specific booking manual update
     *
     * @param integer|string $bookingId
     * 
     * @return array
     */
    public function getManualUpdate($bookingId)
    {
        try {
            $vars['assignedGuides'] = TipService::getBookingAssignedGuides($bookingId);
            $vars['bookingInfo'] = DB::table('tour_bookings_master AS tbm')
                ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
                ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
                ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
                ->where('tbm.id', $bookingId)
                ->select('tbm.id', 'tbm.name AS customer_name', 'tbm.tourists','tbm.infants', 'tp.name as package_name', 'tsd.date')
                ->first();
                
            $checkInsResult = BoardService::checkInStatus($vars['bookingInfo']);
            $vars['bookingInfo']->checkInsCount = $checkInsResult->checkInsCount 
                ?? ['1' => 0, '2' => 0, 'A' => 0];

            $absentCount = $vars['bookingInfo']->checkInsCount['A'] ?? 0;
            $vars['bookingInfo']->tourists = max(0, $vars['bookingInfo']->tourists - $absentCount);

            //INFANTS CHECK-IN LOGIC
            $checkInsInfantsResult = BoardService::checkInInfantsStatus($vars['bookingInfo']);
            $vars['bookingInfo']->checkInsInfantsCount = $checkInsInfantsResult->checkInsInfantsCount
                ?? ['1' => 0, '2' => 0, 'A' => 0];

            $absentInfants = $vars['bookingInfo']->checkInsInfantsCount['A'] ?? 0;
            $vars['bookingInfo']->infants = max(0, ($vars['bookingInfo']->infants ?? 0) - $absentInfants );

            $bookingPrefix = bookingPrefixSetting();
            $vars['bookingInfo']->id = $bookingPrefix . $vars['bookingInfo']->id;
            return $vars;
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Swap bookings
     * 
     * @param Request $request
     */
    public function swapBookings(Request $request)
    {
        DB::beginTransaction();
        try {
            $fromStationId  = $request->from_station_id;
            $fromGuideId    = $request->from_guide_id;
            $fromBookingIds = $request->from_booking_ids ?? [];

            $toStationId    = $request->to_station_id;
            $toGuideId      = $request->to_guide_id;
            $toBookingIds   = $request->to_booking_ids ?? [];

            $timeFrom       = $request->time_from;
            $date           = $request->date;

            // 1. Unassign FROM station
            DB::table('booking_tour_guides')
                ->whereIn('booking_id', $fromBookingIds)
                ->where('station_id', $fromStationId)
                ->where('guide_id', $fromGuideId)
                ->where('time_from', $timeFrom)
                ->where('date', $date)
                ->delete();

            // 2. Unassign TO station
            DB::table('booking_tour_guides')
                ->whereIn('booking_id', $toBookingIds)
                ->where('station_id', $toStationId)
                ->where('guide_id', $toGuideId)
                ->where('time_from', $timeFrom)
                ->where('date', $date)
                ->delete();

            // --- Helper closure to fetch remaining tourists ---
            $getRemainingCounts = function ($bookingId) use ($timeFrom, $date) {
                $row = DB::table('tour_bookings_master as tbm')
                    ->leftJoin('booking_tour_guides as btg', function ($join) use ($timeFrom, $date) {
                        $join->on('btg.booking_id', '=', 'tbm.id')
                             ->where('btg.time_from', $timeFrom)
                             ->where('btg.date', $date);
                    })
                    ->where('tbm.id', $bookingId)
                    ->groupBy('tbm.id', 'tbm.tourists', 'tbm.infants')
                    ->selectRaw('
                        tbm.id,
                        GREATEST(tbm.tourists - COALESCE(SUM(btg.tourists), 0), 0) AS remaining_tourists,
                        GREATEST(tbm.infants - COALESCE(SUM(btg.infants), 0), 0) AS remaining_infants
                    ')
                    ->first();

                if (!$row) {
                    return ['tourists' => 0, 'infants' => 0];
                }

                $checkInsResult = BoardService::checkInStatus($row);
                $checkInsCount  = $checkInsResult->checkInsCount ?? ['1' => 0, '2' => 0, 'A' => 0];

                $absentCount = $checkInsCount['A'] ?? 0;

                return [
                    'tourists' => max((int) $row->remaining_tourists - $absentCount, 0),
                    'infants'  => (int) $row->remaining_infants,
                ];
            };

            // 3. Assign FROM bookings to TO station
            foreach ($fromBookingIds as $bid) {
                $remaining = $getRemainingCounts($bid);

                if ($remaining['tourists'] > 0 || $remaining['infants'] > 0) {
                    $tourBooking = TourBooking::find($bid); 
                    BoardService::blockNextTimeSlot($tourBooking->tour_package_id, $date, $timeFrom, $toGuideId, $toStationId, $bid );

                    DB::table('booking_tour_guides')->updateOrInsert(
                        [
                            'booking_id' => $bid,
                            'guide_id'   => $toGuideId,
                            'station_id' => $toStationId,
                            'time_from'  => $timeFrom,
                            'date'       => $date,
                        ],
                        [
                            'tourists'   => $remaining['tourists'],
                            'infants'    => $remaining['infants'],
                            'updated_at' => now(),
                        ]
                    );

                    TipService::updateDistributionAmounts($bid);
                }
            }

            // 4. Assign TO bookings to FROM station
            foreach ($toBookingIds as $bid) {
                $remaining = $getRemainingCounts($bid);

                if ($remaining['tourists'] > 0 || $remaining['infants'] > 0) {
                    $tourBooking = TourBooking::find($bid); 
                    BoardService::blockNextTimeSlot($tourBooking->tour_package_id, $date, $timeFrom, $fromGuideId, $fromStationId, $bid);

                    DB::table('booking_tour_guides')->updateOrInsert(
                        [
                            'booking_id' => $bid,
                            'guide_id'   => $fromGuideId,
                            'station_id' => $fromStationId,
                            'time_from'  => $timeFrom,
                            'date'       => $date,
                        ],
                        [
                            'tourists'   => $remaining['tourists'],
                            'infants'    => $remaining['infants'],
                            'updated_at' => now(),
                        ]
                    );

                    TipService::updateDistributionAmounts($bid);
                }
            }

            DB::commit();

            // Notify board: bookings swapped between guides
            BoardUpdated::dispatch($date, $timeFrom, $this->boardChannel());
            return response()->json([
                'success' => true,
                'message' => 'Bookings swapped successfully.'
            ]);
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    public function guides(Request $request)
    {
        $assignedGuides = DB::table('tour_operator_staff as tos')
            ->join('model_has_roles AS mhr', 'mhr.model_id', '=', 'tos.id')
            ->join('roles AS r', 'r.id', '=', 'mhr.role_id')
            ->join('booking_tour_guides as btg', 'btg.guide_id', '=', 'tos.id')
            ->join('stations as s', 's.id', '=', 'btg.station_id')
            // JOIN guide specific capacity (override)
            ->leftJoin('guide_specific_capacity as gsc', function ($join) use ($request) {
                $join->on('gsc.guide_id', '=', 'btg.guide_id')
                     ->on('gsc.time_from', '=', 'btg.time_from')
                     ->where('gsc.date', '=', $request->date);
            })
            ->where('btg.time_from', $request->time_from)
            ->where('btg.date', $request->date)
            ->where('tos.status', 'Active')
            ->where('r.name', 'tour-guide')
            ->select(
                'tos.id',
                's.name as stationName',
                DB::raw("CONCAT(tos.first_name, ' ', tos.last_name) AS guideName"),
                // effective capacity (override > default)
                DB::raw('COALESCE(gsc.capacity, tos.capacity) AS capacity'),
                DB::raw('COALESCE(SUM(btg.tourists), 0) as assigned_tourists')
            )
            ->groupBy(
                'tos.id',
                's.name',
                DB::raw('COALESCE(gsc.capacity, tos.capacity)'),
                'tos.first_name',
                'tos.last_name'
            )
            ->get();

        return $assignedGuides;
    }

    /**
     * Search booking
     * 
     * @param Request $request
     */
    public function searchBooking(Request $request)
    {
        $searchValue = $request->input('query');
        $date        = $request->input('date');
        $userType    = userType();
        $authId      = Auth::id();

        // Base query for bookings
        $bookingsQuery = DB::table('tour_bookings_master as tbm')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->where('tst.date', $date)
            ->where('tbm.status', '!=', 'Cancelled')
            ->where('tp.include_in_operation_board', 1)
            ->where(function ($q) use ($searchValue) {
                $q->where('tbm.id', $searchValue)
                  ->orWhere('tbm.email', $searchValue);
            });

        $assignedBookings = $bookingsQuery
            ->select(
                'tbm.id',
                'tbm.name',
                'tbm.tourists',
                'tbm.infants',
                'tp.name as tour_package',
                'tp.id as tour_package_id',
                'tst.time_from',
                'tst.date'
            )
            ->groupBy('tbm.id', 'tbm.name', 'tbm.tourists', 'tp.name', 'tp.id', 'tst.time_from', 'tst.date')
            ->get()
            ->map(function ($row) {
                $checkInsResult = BoardService::checkInStatus($row);
                $row->checkInsCount = $checkInsResult->checkInsCount 
                    ?? ['1' => 0, '2' => 0, 'A' => 0];

                $absentCount = $row->checkInsCount['A'] ?? 0;

                $checkInsInfantsResult = BoardService::checkInInfantsStatus($row);
                $row->checkInsInfantsCount = $checkInsInfantsResult->checkInsInfantsCount ?? ['1' => 0, '2' => 0, 'A' => 0];
                $absentInfants = $row->checkInsInfantsCount['A'] ?? 0;
                $row->infants = max(0, ($row->infants ?? 0) - $absentInfants);
                return $row;
            });

        // Assigned guides for the booking
        $assignedGuidesQuery = DB::table('tour_operator_staff as tos')
            ->join('booking_tour_guides as btg', 'btg.guide_id', '=', 'tos.id')
            ->where('tos.status', 'Active')
            ->where('btg.booking_id', $searchValue);

        $assignedGuides = $assignedGuidesQuery
            ->select('tos.id', DB::raw("CONCAT(tos.first_name, ' ', tos.last_name) AS guideName"))
            ->get();

        // Load slot times for that date and package
        $slotTimes = [];
        if ($assignedBookings->isNotEmpty()) {
            $tourPackageId = $assignedBookings->first()->tour_package_id;
            $slotTimes = BoardService::getOnlySlotTimesByDate($date, $tourPackageId);
        }

        return response()->json([
            'success'        => $assignedBookings->isNotEmpty(),
            'bookings'       => $assignedBookings,
            'assignedGuides' => $assignedGuides,
            'slotTimes'      => $slotTimes,
        ]);
    }

    /**
     * Transfer booking
     * 
     * @param Request $request
     */
    public function transferBooking(Request $request)
    {
        $bookingIds = $request->input('booking_ids', []);
        $timeFrom   = $request->input('time_from');
        $date       = $request->input('date');
        $guideId    = $request->input('guide_id');

        if (empty($bookingIds) || !$timeFrom || !$date || !$guideId) {
            return response()->json([
                'success' => false,
                'message' => 'Missing required fields'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Fetch bookings (id, tourists, tour_package_id)
            $bookings = DB::table('tour_bookings_master as tbm')
                ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
                ->whereIn('tbm.id', $bookingIds)
                ->select('tbm.id', 'tbm.tourists','tbm.infants', 'tbm.tour_package_id', 'tst.date', 'tst.time_from')
                ->get()
                ->keyBy('id'); // quick lookup by id
            // Ensure all requested booking ids exist
            foreach ($bookingIds as $bid) {
                if (!isset($bookings[$bid])) {
                    throw new \Exception("Booking ID {$bid} not found.");
                }
            }

            // Incoming tourists total
            $incomingTotal = $bookings->sum('tourists');

            // Compute already assigned tourists for this guide at the target time/date
            $alreadyAssigned = (int) DB::table('booking_tour_guides')
                ->where('guide_id', $guideId)
                ->where('time_from', $timeFrom)
                ->where('date', $date)
                ->sum('tourists');

            // Guide capacity
            $guideCapacity = (int) DB::table('tour_operator_staff as tos')
                ->leftJoin('guide_specific_capacity as gsc', function ($join) use ($guideId, $date, $timeFrom) {
                    $join->on('gsc.guide_id', '=', 'tos.id')
                         ->where('gsc.date', '=', $date)
                         ->where('gsc.time_from', '=', $timeFrom);
                })
                ->where('tos.id', $guideId)
                ->where('tos.status', 'Active')
                ->value(DB::raw('COALESCE(gsc.capacity, tos.capacity)'));


            if ($guideCapacity > 0 && ($alreadyAssigned + $incomingTotal) > $guideCapacity) {
                return response()->json([
                    'success' => false,
                    'message' => "Guide capacity exceeded. Capacity: {$guideCapacity}, already assigned: {$alreadyAssigned}, incoming: {$incomingTotal}."
                ], 422);
            }

            //  Validate station packages match booking packages
            // Get station_id for this guide/time/date
            $data = DB::table('booking_tour_guides')
                ->where('guide_id', $guideId)
                ->where('time_from', $timeFrom)
                ->where('date', $date)
                ->first();

            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'No station assigned for this guide at selected date/time.'
                ], 422);
            }

            // Fetch station packages
            $station = DB::table('stations')
                ->where('id', $data->station_id)
                ->select('tour_packages', 'name')
                ->first();

            if (!$station) {
                throw new \Exception("Station not found.");
            }

            // Resolve packages: specific override (date+time) > station default
            $overridePackagesJson = DB::table('board_station_specific_packages')
                ->where('station_id', $data->station_id)
                ->where('date', $date)
                ->where('time_from', $timeFrom)
                ->value('packages');

            $rawPackages = $overridePackagesJson ?? $station->tour_packages;

            $stationPackages = array_values(array_map('intval', json_decode($rawPackages, true) ?? []));

            // Now validate each booking package
            foreach ($bookings as $booking) {

                if (!in_array((int) $booking->tour_package_id, $stationPackages)) {

                    return response()->json([
                        'success' => false,
                        'message' => "Cannot transfer booking ID {$booking->id}. 
                                      Station '{$station->name}' does not support this package."
                    ], 422);
                }
            }

            // Delete old assignments for these bookings (set booking_id = null, tourists = 0)
            // Fetch old assignments
            $oldAssignments = DB::table('booking_tour_guides')
                ->whereIn('booking_id', $bookingIds)
                ->where('guide_id','!=', 0)
                ->get();

            // Un-assign old guide & station connections
            foreach ($oldAssignments as $assign) {

                $fakeRequest = new Request([
                    'station_id'    => $assign->station_id,
                    'tour_guide_id' => $assign->guide_id,
                    'time_from'     => $assign->time_from,
                    'date'          => $assign->date,
                    'tour_booking_id' => $bookingIds[0]
                ]);

                app()->call([$this, 'unAssignBooking'], ['request' => $fakeRequest]);
            }

            // Prepare repository instance
            $repo = app(\App\Repositories\TourBooking\V3\TourBookingRepository::class);

            // For each booking: find corresponding tour_slot_times.id, call reschedule, then insert new booking_tour_guides row
            foreach ($bookings as $booking) {
                $checkInsResult = BoardService::checkInStatus($booking);
                $checkInsCount = $checkInsResult->checkInsCount 
                    ?? ['1' => 0, '2' => 0, 'A' => 0];

                $absentCount = $checkInsCount['A'] ?? 0;
                $tourists = $booking->tourists - $absentCount;

                $checkInsInfantsResult = BoardService::checkInInfantsStatus($booking);
                $booking->checkInsInfantsCount = $checkInsInfantsResult->checkInsInfantsCount ?? ['1' => 0, '2' => 0, 'A' => 0];
                $absentInfants = $booking->checkInsInfantsCount['A'] ?? 0;
                $totalInfants = max(0, ($booking->infants ?? 0) - $absentInfants);

                // Already assigned infants (if partially assigned earlier)
                $assignedInfants = (int) DB::table('booking_tour_guides')
                    ->where('booking_id', $booking->id)
                    ->sum('infants');

                $infants = max($totalInfants - $assignedInfants, 0);

                $data = DB::table('booking_tour_guides')
                        ->where('guide_id', $guideId)
                        ->where('time_from', $timeFrom)
                        ->where('date', $date)
                        ->first();
                // find slot time id for this booking's package / date / time_from
                $slot = DB::table('tour_slot_times')
                    ->where('tour_package_id', $booking->tour_package_id)
                    ->where('date', $date)
                    ->where('time_from', $timeFrom)
                    ->select('id')
                    ->first();

                if (!$slot) {
                    throw new \Exception("Slot not found for booking {$booking->id} (package {$booking->tour_package_id}) at {$date} {$timeFrom}.");
                }

                // create a tiny request-like object for repository reschedule() method
                $rsReq = new Request([
                    'tour_slot_time' => (int) $slot->id,
                    'send_rb_mail'   => 'Yes', // avoid sending emails
                    'api'            => 1,     // ensure repository returns instead of redirecting
                ]);

                // Skip reschedule if booking already has same date & time
                $rescheduled = false;
                if ($booking->date != $date || $booking->time_from != $timeFrom) {

                    $res = $repo->reschedule($rsReq, (int) $booking->id);
                    $rescheduled = true;

                }
                // reschedule will throw on validation errors; if it returns something you can inspect $res if required

                // Check if a placeholder exists: same (guide, station, time, date) but booking_id = NULL
                $existing = DB::table('booking_tour_guides')
                    ->where('guide_id', $guideId)
                    ->where('station_id', $data->station_id)     // use the station_id you captured earlier
                    ->where('time_from', $timeFrom)
                    ->where('date', $date)
                    ->whereNull('booking_id')
                    ->first();

                if ($existing) {
                    // Update existing placeholder row
                    DB::table('booking_tour_guides')
                        ->where('id', $existing->id)
                        ->update([
                            'booking_id' => $booking->id,
                            'tourists'   => $tourists,
                            'infants'    => $infants,
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('booking_tour_guides')->updateOrInsert(
                        [
                            'booking_id' => $booking->id,
                            'guide_id'   => $guideId,
                            'station_id' => $data->station_id,
                            'time_from'  => $timeFrom,
                            'date'       => $date,
                        ],
                        [
                            'tourists'   => $tourists,
                            'infants'    => $infants,
                            'updated_at' => now(),
                        ]
                    );
                }

                BoardService::blockNextTimeSlot($booking->tour_package_id, $date, $timeFrom, $guideId, $data->station_id, $booking->id);

                $guide = Staff::findOrFail($guideId);
                $guideName = $guide->first_name. ' ' .$guide->last_name;

                $note = "Tour guide $guideName has been successfully assigned to the booking.";
                HistoryService::storeNoteInHistory($note, $booking->id);
                // Update distribution amounts after saving tour guide
                TipService::updateDistributionAmounts($booking->id);
            }

            DB::commit();

            $message = 'Bookings successfully transferred';
            if ($rescheduled) {
                $message .= ' and rescheduled';
            }

            // Notify board: booking transferred to another guide/slot
            BoardUpdated::dispatch($date, $timeFrom, $this->boardChannel());
            return response()->json(['success' => true, 'message' => $message]);
        } catch (\Exception $exception) {
            DB::rollBack();
           Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Tour guide board portal
     * 
     * @param Request $request
     */
    public function guideBoard(Request $request)
    {
        try {
            $page_title = "Board Guide";

            $date = $request->date
                ? Carbon::parse($request->date)
                : today();


            $monthlyAssignments = '';
            $tz = '';
            if (!isset($request->date)) {
                $monthStart = $date->copy()->startOfMonth();
                $monthEnd = $date->copy()->endOfMonth();
                $tourOperator = TourOperatorService::getTourOperator();
                $tz = $tourOperator->timezone;
                // Current Phoenix timezone date (optional — use system or configured timezone)
                $today = now()->setTimezone($tz)->toDateString();

                // Fetch all guides’ assigned tourists for the entire month
                $monthlyAssignmentsQuery = DB::table('booking_tour_guides as btg')
                    ->join('tour_bookings_master as tbm', 'tbm.id', '=', 'btg.booking_id')
                    ->select(
                        'btg.date',
                        DB::raw('SUM(btg.tourists + btg.infants) as total_assigned_tourists')
                    )
                    ->whereBetween('btg.date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                    ->where('btg.guide_id', Auth::id());

                $monthlyAssignments = $monthlyAssignmentsQuery
                ->groupBy('btg.date')
                ->pluck('total_assigned_tourists', 'btg.date');
            }

            // Fetch detailed records for the selected date
            $details = DB::table('booking_tour_guides as btg')
            ->leftJoin('tour_bookings_master as tbm', 'tbm.id', '=', 'btg.booking_id')
            ->leftJoin('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')

            ->join('stations as s', 's.id', '=', 'btg.station_id')

            //  station name override
            ->leftJoin('board_station_specific_name as bssn', function ($join) use ($date) {
                $join->on('bssn.station_id', '=', 'btg.station_id')
                        ->on('bssn.time_from', '=', 'btg.time_from')
                        ->where('bssn.date', '=', $date->toDateString());
                     
            })

            ->join('tour_operator_staff as tos', 'tos.id', '=', 'btg.guide_id')

            ->leftJoin('guide_specific_capacity as gsc', function ($join) use ($date) {
                $join->on('gsc.guide_id', '=', 'btg.guide_id')
                     ->on('gsc.time_from', '=', 'btg.time_from')
                     ->where('gsc.date', '=', $date->toDateString());
            })

            ->where('btg.date', $date->toDateString())
            ->where('btg.guide_id', Auth::id())
            ->where('tos.status', 'Active')

            ->get([
                'tbm.id',
                'tbm.name',
                'tbm.email',
                DB::raw("CONCAT(tos.first_name, ' ', tos.last_name) AS guide_name"),
                'btg.guide_id',
                'btg.time_from',
                'btg.date',
                DB::raw('COALESCE(gsc.capacity, tos.capacity) AS capacity'),
                'btg.tourists as assigned_tourists',
                'btg.infants',
                'tp.name as tour_package',

                // This will now correctly show override
                DB::raw('COALESCE(bssn.name, s.name) as station_name'),
            ]);


            // Attach check-in data
            $details = $details->map(function ($booking) {
                $checkInsResult = BoardService::checkInStatus($booking);
                $booking->checkInsCount = $checkInsResult->checkInsCount ?? ['1' => 0, '2' => 0, 'A' => 0];
                $booking->assignedGuides = TipService::getBookingAssignedGuides($booking->id, $booking->date, $booking->time_from);
                return $booking;
            });

            // Group and summarize
            $grouped = $details->groupBy('time_from');
            $grouped = $grouped
            // Proper time-based sorting
            ->sortKeysUsing(function ($a, $b) {
                return strtotime($a) <=> strtotime($b);
            })
            ->map(function ($items) {
                if ($items->count() > 1) {
                    return $items->filter(function ($item) {
                        return !is_null($item->id);
                    })->values();
                }

                // Single record → keep even if id is null
                return $items;
            })
            // Remove empty slots
            ->filter(function ($items) {
                return $items->isNotEmpty();
            });

            $firstBooking = $details->first();
            $selectedDate = $firstBooking
                ? Carbon::parse($firstBooking->date)->format('l, F d, Y')
                : $date->format('l, F d, Y');

            $totalAssigned = $details->sum('assigned_tourists');
            $capacity = $firstBooking->capacity ?? 0;
            $totalCapacity = $capacity * $grouped->count();
            $guide = Staff::find(Auth::id());
            $guideName = $guide->first_name.' '. $guide->last_name;

            // Return data for AJAX
            if ($request->ajax()) {
                return response()->json([
                    'html' => view('partials.board.guide-data', compact('grouped', 'selectedDate', 'totalAssigned', 'totalCapacity', 'guideName', 'guide'))->render(),
                    'monthlyAssignments' => $monthlyAssignments,
                    'timezone' => $tz,
                ]);
            }

            return view('partials.board.guide', compact('page_title', 'tz'));
        } catch (Exception $exception) {
                Log::error($exception);
                throw new GeneralCoreException($exception);
        }  
    }

    public function timeSlotTransfer(Request $request)
    {
        try {
            $date = $request->date
                ? Carbon::parse($request->date)->toDateString()
                : today()->toDateString();

            // Step 1: Get all time slots for this date
            $getSlots = BoardService::getSlots($date);

            $result = [];
            $currentTime = Carbon::now()->format('H:i:s'); // current time for filtering

            foreach ($getSlots as $slot) {
                $slotDate = $slot->date;
                $timeFrom = $slot->time_from;

                // Step 2: Build query for bookings
                $bookings = DB::table('tour_bookings_master as tbm')
                    ->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
                    ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
                    ->where('tbm.status', '!=', 'Cancelled')
                    ->where('tst.date', $slotDate)
                    ->where('tst.time_from', $timeFrom)
                    ->where('tp.include_in_operation_board', 1)
                    ->select(
                        'tbm.id',
                        'tbm.name',
                        'tp.name as tour_package',
                        'tbm.tourists',
                        'tbm.infants',
                    )
                    ->groupBy('tbm.id', 'tp.name', 'tbm.tourists')
                    ->get()
                    ->map(function ($row) {
                        $checkInsResult = BoardService::checkInStatus($row);
                        $row->checkInsCount = $checkInsResult->checkInsCount ?? ['1' => 0, '2' => 0, 'A' => 0];

                        $absentCount = $row->checkInsCount['A'] ?? 0;
                        // $row->tourists = max(0, $row->tourists - $absentCount);

                        $checkInsInfantsResult = BoardService::checkInInfantsStatus($row);
                        $row->checkInsInfantsCount = $checkInsInfantsResult->checkInsInfantsCount ?? ['1' => 0, '2' => 0, 'A' => 0];
                        $absentInfants = $row->checkInsInfantsCount['A'] ?? 0;
                        $row->infants = max(0, ($row->infants ?? 0) - $absentInfants);

                        return $row;
                    });

                // Step 3: Attach non-empty results
                if ($bookings->isNotEmpty()) {
                    $result[$slot->formatted_time] = $bookings;
                }
            }

            return response()->json($result);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * store the board station packages.
     *
     * @param $request
     */

    public function storeStationPackage(Request $request)
    {
        $pkg = BoardStationPackage::create([
            'name' => $request->name,
            'assign_packages' => $request->assign_packages ?? [],
        ]);

       $assigned =TourPackage::whereIn('id', $pkg->assign_packages ?? [])->pluck('name')->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Record added successfully',
            'data' => [
                'id' => $pkg->id,
                'name' => $pkg->name,
                'assigned_names' => $assigned
            ]
        ]);
    }

    /**
     * show the board station packages.
     *
     * @param $id
     */

    public function showEditStationPackage($id)
    {
        $pkg = BoardStationPackage::find($id);
        return response()->json(['success' => true, 'data' => $pkg]);
    }

    /**
     * Update the board station packages.
     *
     * @param $id
     * @param $request
     */
    public function updateStationPackage(Request $request, $id)
    {
        $pkg = BoardStationPackage::findOrFail($id);
        $pkg->update([
            'name' => $request->name,
            'assign_packages' => $request->assign_packages ?? [],
        ]);

        $assigned = TourPackage::whereIn('id', $pkg->assign_packages ?? [])->pluck('name')->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Record updated successfully',
            'data' => [
                'id' => $pkg->id,
                'name' => $pkg->name,
                'assigned_names' => $assigned
            ]
        ]);
    }

    /**
     * delete the board station packages.
     *
     * @param $id
     */
    public function destroyStationPackage($id)
    {
        BoardStationPackage::find($id)->delete();
        return response()->json(['success' => true, 'message' => 'Record deleted successfully']);
    }

    /**
     * Manual update assign multiple guide to large booking
     *
     * @param Request $request
     * @param int $id
     * @return mixed
     * 
     */
    public function manualUpdate(Request $request, int $id)
    {
        try {
            if (empty($request->all())) {
                throw new Exception("Select tour guide to add in this booking.");
            }

            $guides = $request->guides;
            $assignedTourGuides = DB::table('booking_tour_guides')
                ->where('booking_id', $id)
                ->pluck('guide_id')
                ->toArray();

            if (empty($request->guides)) {
                throw new Exception("Please select a tour guide to assign in this booking.");
            }
            DB::beginTransaction();
            $timeSlot =  DB::table('tour_slot_times as tst')
                    ->join('tour_bookings_master as tbm', 'tbm.tour_slot_id', '=', 'tst.id')
                    ->where('tbm.id', $id)
                    ->first(['tst.time_from', 'tst.date']);  

            BoardService::validateGuideAssignment($request);
            
            foreach ($guides as $guide) {
                $overBooked = $guide['over_booked'] ?? false;
                if (isset($guide['id']) && $guide['id'] > 0 && isset($guide['is_deleted']) && $guide['is_deleted'] == 1) {
                    $tourGuide = BookingTourGuide::find($guide['id']);
                    $guideAssignments = BookingTourGuide::where('station_id', $tourGuide->station_id)
                        ->where('guide_id', $tourGuide->guide_id)
                        ->where('time_from', $tourGuide->time_from)
                        ->where('date', $tourGuide->date)
                        ->get();

                    $tourGuide = BookingTourGuide::where('id', $guide['id'])
                        ->first();

                    $guide = Staff::findOrFail($tourGuide->guide_id);
                    $guideName = $guide->first_name. ' ' .$guide->last_name;

                    // Remove or reset guide record
                    if ($guideAssignments->count() > 1) {
                        $tourGuide->delete();
                    } else {
                        $tourGuide->booking_id = null;
                        $tourGuide->tourists = 0;
                        $tourGuide->infants = 0;
                        $tourGuide->save();

                        BoardService::deleteBlockNextTimeSlot($tourGuide->date, $tourGuide->guide_id, $timeSlot->time_from, $tourGuide->station_id, $id);
                    }

                    $note = "Tour guide $guideName has been successfully removed from the booking.";
                    HistoryService::storeNoteInHistory($note, $id);
                } else {

                    if (!in_array($guide['guide_id'], $assignedTourGuides)) {
                         $checkGuide = BookingTourGuide::where('guide_id', $guide['guide_id'])->where('time_from', $request->time_from)->where('date', $request->date)
                         ->first();

                        if ($checkGuide && $overBooked != 'true' && $checkGuide->booking_id == null) {

                            $checkGuide->booking_id = $id;
                            $checkGuide->tourists = $guide['tourists'];
                            $checkGuide->infants = $guide['infants'];
                            $checkGuide->save();

                        } else {

                            DB::table('booking_tour_guides')->updateOrInsert(
                                [
                                    'booking_id' => $id,
                                    'guide_id'   => $guide['guide_id'],
                                    'station_id' => $checkGuide->station_id ?? null,
                                    'time_from'  => $timeSlot->time_from,
                                    'date'       => $timeSlot->date,
                                ],
                                [
                                    'tourists'   => $guide['tourists'],
                                    'infants'    => $guide['infants'],
                                    'updated_at' => now(),
                                ]
                            );
                        }

                        $tourBooking = TourBooking::find($id);
                        BoardService::blockNextTimeSlot($tourBooking->tour_package_id, $timeSlot->date, $timeSlot->time_from, $guide['guide_id'], $checkGuide->station_id ?? null, $id);

                        $guide = Staff::findOrFail($guide['guide_id']);
                        $guideName = "$guide->first_name $guide->last_name";

                        $note = "Tour guide $guideName has been successfully assigned to the booking.";
                        HistoryService::storeNoteInHistory($note, $id);
                    } else {
                        DB::table('booking_tour_guides')
                            ->where('booking_id', $id)
                            ->where('guide_id', $guide['guide_id'])->update([
                                'tourists' => $guide['tourists'],
                                'infants'  => $guide['infants'],
                                'updated_at' => mysqlDT(),
                            ]);
                    }
                }
            }
            DB::commit();

            // Update distribution amounts after saving tour guide
            TipService::updateDistributionAmounts($id);
            DB::commit();

            // Notify board: manual booking update applied (tourists, guide, station)
            BoardUpdated::dispatch($timeSlot->date, $timeSlot->time_from, $this->boardChannel());
            return [
                'booking_id' => $id
            ];
        } catch (Exception $exception) {
            Log::error($exception);
            DB::rollBack();
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Update the guide status
     * @param Request $request
     */
    public function updateGuideStatus(Request $request)
    {
        try {
            $id = $request->id;

            $staff = Staff::findOrFail($id);
            $staff->guide_status = $request->guide_status;
            $staff->save();

            if ($request->guide_status === 'Inactive') {

                $guideQuery = BookingTourGuide::where('date', $request->todayDate)
                    ->where('guide_id', $id)
                    ->where('check_in_status', '!=', 'Completed');

                // Get records for history notes
                $guideDetails = $guideQuery->get();

                foreach ($guideDetails as $guide) {
                    $guideName = $staff->first_name . ' ' . $staff->last_name;
                    $note = "Tour guide {$guideName} has been successfully removed from the booking.";
                    HistoryService::storeNoteInHistory($note, $guide->booking_id);
                }

                // Update using the query (this works)
                $guideQuery->update(['guide_id' => null]);
            }

            return true;

        } catch (\Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Specific stations name update
     * @param Request $request
     */

    public function specificStationsUpdateName(Request $request)
    {
        try {

            // If station_specific == 1 → update stations table
            if ((int) $request->station_specific === 1) {
                DB::table('stations')
                    ->where('id', $request->station_id)
                    ->update([
                        'name'       => $request->station_name,
                        'updated_at' => now(),
                    ]);

                return response()->json(['success' => true]);
            }

            // station_specific != 1 → use override table
            $station = DB::table('stations')
                ->where('id', $request->station_id)
                ->first();

            if (!$station) {
                return response()->json(['success' => false, 'message' => 'Station not found'], 404);
            }

            $defaultName  = trim($station->name);
            $incomingName = trim($request->station_name);

            // Find existing override
            $override = DB::table('board_station_specific_name')
                ->where('station_id', $request->station_id)
                ->where('date', $request->date)
                ->where('time_from', $request->time_from)
                ->first();

            // If incoming name matches default → delete override if exists
            if ($incomingName === $defaultName) {
                if ($override) {
                    DB::table('board_station_specific_name')
                        ->where('id', $override->id)
                        ->delete();
                }

                // Notify board: station name reverted to default
                BoardUpdated::dispatch($request->date, $request->time_from, $this->boardChannel());
                return response()->json(['success' => true]);
            }

            // Incoming name differs from default → insert or update override
            if ($override) {
                DB::table('board_station_specific_name')
                    ->where('id', $override->id)
                    ->update([
                        'name'       => $incomingName,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('board_station_specific_name')
                    ->insert([
                        'station_id' => $request->station_id,
                        'name'       => $incomingName,
                        'date'       => $request->date,
                        'time_from'  => $request->time_from,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            // Notify board: station name override saved for this slot
            BoardUpdated::dispatch($request->date, $request->time_from, $this->boardChannel());
            return response()->json(['success' => true]);

        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Get the specific package override for a station on a given date+time.
     */
    public function getStationSpecificPackages(Request $request)
    {
        $override = BoardStationSpecificPackage::where('station_id', $request->station_id)
            ->where('date', $request->date)
            ->where('time_from', $request->time_from)
            ->first();

        return response()->json([
            'packages' => $override ? $override->packages : null,
        ]);
    }

    /**
     * Save or clear the specific package override for a station on a given date+time.
     * Mirrors specificStationsUpdateName: if selected packages match the station's
     * default tour_packages, delete the override (revert to default) instead of storing it.
     */
    public function updateStationSpecificPackages(Request $request)
    {
        try {
            $incoming = array_values(array_map('intval', array_filter((array) $request->input('packages', []))));
            sort($incoming);

            $station = DB::table('stations')->where('id', $request->station_id)->first();
            if (!$station) {
                return response()->json(['success' => false, 'message' => 'Station not found'], 404);
            }

            $defaultPackages = array_values(array_map('intval', json_decode($station->tour_packages ?? '[]', true) ?? []));
            sort($defaultPackages);

            $override = BoardStationSpecificPackage::where('station_id', $request->station_id)
                ->where('date', $request->date)
                ->where('time_from', $request->time_from)
                ->first();

            // If incoming matches station default → delete override (no need to store it)
            if ($incoming === $defaultPackages) {
                if ($override) {
                    $override->delete();
                }
            } elseif ($override) {
                $override->update(['packages' => $incoming]);
            } else {
                BoardStationSpecificPackage::create([
                    'station_id' => $request->station_id,
                    'date'       => $request->date,
                    'time_from'  => $request->time_from,
                    'packages'   => $incoming,
                ]);
            }

            BoardUpdated::dispatch($request->date, $request->time_from, $this->boardChannel());
            return response()->json(['success' => true, 'message' => 'Station packages updated.']);

        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Guide boad pdf download
     * @param Request $request
     */
    public function downloadGuideBoardPdf(Request $request)
    {
        // Reuse logic of guideBoard()
        $date = $request->date
            ? Carbon::parse($request->date)
            : today();

        $guideId = Auth::id();

        // Fetch guide timezone
        $tourOperator = TourOperatorService::getTourOperator();
        $tz = $tourOperator->timezone;

        // Fetch detailed records
        $details = DB::table('booking_tour_guides as btg')
            ->leftJoin('tour_bookings_master as tbm', 'tbm.id', '=', 'btg.booking_id')
            ->leftJoin('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')

            ->join('stations as s', 's.id', '=', 'btg.station_id')

            // station name override 
            ->leftJoin('board_station_specific_name as bssn', function ($join) use ($date) {
                $join->on('bssn.station_id', '=', 'btg.station_id')
                        ->on('bssn.time_from', '=', 'btg.time_from')
                        ->where('bssn.date', '=', $date->toDateString());
                     
            })

            ->join('tour_operator_staff as tos', 'tos.id', '=', 'btg.guide_id')

            // JOIN guide specific capacity (override)
            ->leftJoin('guide_specific_capacity as gsc', function ($join) use ($date) {
                $join->on('gsc.guide_id', '=', 'btg.guide_id')
                     ->on('gsc.time_from', '=', 'btg.time_from')
                     ->where('gsc.date', '=', $date->toDateString());
            })

            ->where('tos.status', 'Active')
            ->where('btg.date', $date->toDateString())
            ->where('btg.guide_id', $guideId)

            ->get([
                'tbm.id',
                'tbm.name',
                'tbm.email',
                DB::raw("CONCAT(tos.first_name,' ',tos.last_name) AS guide_name"),
                'btg.time_from',
                'btg.tourists as assigned_tourists',
                'btg.infants',
                // effective capacity (override > default)
                DB::raw('COALESCE(gsc.capacity, tos.capacity) AS capacity'),

                'tp.name as tour_package',

                //  final station name
                DB::raw('COALESCE(bssn.name, s.name) as station_name'),

                'btg.date'
            ]);


        // Attach check-in data
        $details = $details->map(function ($booking) {
            $checkInsResult = BoardService::checkInStatus($booking);
            $booking->checkInsCount = $checkInsResult->checkInsCount ?? ['1' => 0, '2' => 0, 'A' => 0];
            $booking->assignedGuides = TipService::getBookingAssignedGuides($booking->id);
            return $booking;
        });

        // Group
        $grouped = $details->groupBy('time_from');
        $grouped = $grouped
        // Proper time-based sorting
        ->sortKeysUsing(function ($a, $b) {
            return strtotime($a) <=> strtotime($b);
        })
        ->map(function ($items) {
            if ($items->count() > 1) {
                return $items->filter(function ($item) {
                    return !is_null($item->id);
                })->values();
            }

            // Single record → keep even if id is null
            return $items;
        })
        // Remove empty slots
        ->filter(function ($items) {
            return $items->isNotEmpty();
        });

        // Header data
        $guide = Staff::find($guideId);
        $guideName = $guide->first_name . ' ' . $guide->last_name;

        $totalAssigned = $details->sum('assigned_tourists');
        $capacity = $details->first()->capacity ?? 0;
        $totalCapacity = $capacity * $grouped->count();

        $selectedDate = $details->first()
            ? Carbon::parse($details->first()->date)->format('l, F d, Y')
            : $date->format('l, F d, Y');

        // Load PDF view
        $pdf = Pdf::loadView('partials.board.pdf.guide-board-pdf', [
            'grouped'        => $grouped,
            'selectedDate'   => $selectedDate,
            'guideName'      => $guideName,
            'totalAssigned'  => $totalAssigned,
            'totalCapacity'  => $totalCapacity,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Guide-Board-'.$selectedDate.'.pdf');
    }

    /**
     * Admin guide management pdf download
     * @param Request $request
     */
    public function downloadAdminBoardPdf(Request $request)
    {
        // Reuse same logic
        $response = json_decode($this->guideManagement($request)->getContent());

        $stations  = collect($response->stations);
        $guides    = collect($response->guides);
        $bookings  = collect($response->bookings);
        $absent    = collect($response->absent_bookings);
        
        $date = $request->date
            ? Carbon::parse($request->date)->format('l, d F Y')
            : now()->format('l, d F Y');

        $pdf = Pdf::loadView('partials.board.pdf.admin-board-pdf', [
            'stations' => $stations,
            'guides' => $guides,
            'unassignedBookings' => $bookings,
            'absentBookings' => $absent,
            'date' => $date,
            'time' => $request->time_from ?? '',
        ])->setPaper('a4', 'portrait');

        return $pdf->download("Guide-Management-{$date}.pdf");
    }

    /**
     * Update the status active all guide
     * @param Request $request
     */
    public function guideStatusActiveAll(Request $request)
    {
        try {

            $staffs = Staff::get();
            foreach ($staffs as $staff) {
                $staff->guide_status = 'Active';
                $staff->save();
            }
            return response()->json(['success' => true]);

        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    
    }

     /**
     * Auto assign function
     * @param Request $request
     */
    public function autoAssignGuide(Request $request)
    {
        try {
            $date = $request->date;
            $timeFrom = $request->time_from;

            // Available guides (already filtered by your service)
            $guides = BoardService::getAvailableGuides($request);
            if ($guides->isEmpty()) {
                return response()->json(['message' => 'No available guides'], 422);
            }

            // Stations (default + date-specific)
            $stations = Station::where(function ($query) use ($date, $timeFrom) {
                $query->where(function ($q) {
                    $q->whereNull('station_date')
                      ->orWhere('station_date', '0000-00-00');
                })
                ->where(function ($q) {
                    $q->whereNull('station_time_from')
                      ->orWhere('station_time_from', '');
                })
                ->orWhere(function ($q) use ($date, $timeFrom) {
                    $q->where('station_date', $date)
                      ->where('station_time_from', $timeFrom);
                });
            })->get();

            if ($stations->isEmpty()) {
                return response()->json(['message' => 'No stations found'], 422);
            }

            // Already assigned for this slot
            $assigned = DB::table('booking_tour_guides')
                ->where('date', $date)
                ->where('time_from', $timeFrom)
                ->get(['guide_id', 'station_id']);

            $usedGuideIds   = $assigned->pluck('guide_id')->filter()->toArray();
            $usedStationIds = $assigned->pluck('station_id')->filter()->toArray();

            // Filter free guides & stations
            $freeGuides = $guides->whereNotIn('id', $usedGuideIds)->values();
            $freeStations = $stations->whereNotIn('id', $usedStationIds)->values();

            if ($freeGuides->isEmpty() || $freeStations->isEmpty()) {
                return response()->json(['assigned' => true,'message' => 'No free guides or stations available']);
            }

            // Assign 1 guide → 1 station
            $assignCount = min($freeGuides->count(), $freeStations->count());

            $rows = [];
            for ($i = 0; $i < $assignCount; $i++) {
                $station = $freeStations[$i];
                $packageIds = json_decode($station->tour_packages, true);

                $maxPackageId = null;
                $maxEndTime   = null;

                foreach ($packageIds as $tourPackageId) {

                    $time = DB::table('tour_slot_times')
                    ->where('tour_package_id', $tourPackageId)
                    ->where('date', $date)
                    ->where('time_from', '<=', $timeFrom)
                    ->where('time_to', '>', $timeFrom)
                    ->orderByDesc('time_to') // safety
                    ->first();
                    if (!$time) {
                        continue;
                    }

                    $endTime = Carbon::createFromFormat('H:i:s', $time->time_to);

                    if ($maxEndTime === null || $endTime->gt($maxEndTime)) {
                        $maxEndTime   = $endTime;
                        $maxPackageId = $tourPackageId;
                    }
                }
                BoardService::blockNextTimeSlot($maxPackageId, $date, $timeFrom, $freeGuides[$i]->id, $freeStations[$i]->id, null);
                
                $rows[] = [
                    'guide_id'    => $freeGuides[$i]->id,
                    'station_id'  => $freeStations[$i]->id,
                    'date'        => $date,
                    'time_from'   => $timeFrom,
                    'booking_id'  => null,
                    'tourists'    => 0,
                    'check_in_status' => 'Pending',
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ];
            }

            // Bulk insert (fast & safe)
            DB::table('booking_tour_guides')->insert($rows);

            // Notify board: auto-assign guide (single guide to all unassigned bookings) completed
            BoardUpdated::dispatch($date, $timeFrom, $this->boardChannel());
            return response()->json(['success' => true, 'message' => 'Auto-assign completed']);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Update the status for ready to 2nd check in
     * @param Request $request
     */
    public function readyStatusUpdate(Request $request)
    {
        try {

            BookingTourGuide::where('time_from', $request->time_from)->where('date', $request->date)->where('station_id', $request->station_id)->where('guide_id', $request->guide_id)->update(['check_in_status' => '2nd Check In']);

            // Notify board: guide ready status updated (2nd check-in)
            BoardUpdated::dispatch($request->date, $request->time_from, $this->boardChannel());
            return true;

       } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Update the guide capacity for specific date and time slot
     * @param Request $request
     */
    public function updateGuideCapacity(Request $request)
    {
        try {

            // Fetch guide (staff)
            $staff = Staff::findOrFail($request->guide_id);

            $defaultCapacity = (int) $staff->capacity;
            $requestedCapacity = (int) $request->capacity;

            // Find existing override record
            $override = GuideSpecificCapacity::where('guide_id', $request->guide_id)
                // ->where('station_id', $request->station_id)
                ->where('time_from', $request->time_from)
                ->where('date', $request->date)
                ->first();

            // If requested == default capacity
            if ($requestedCapacity === $defaultCapacity) {

                // If override exists → delete it
                if ($override) {
                    $override->delete();
                }

                // Notify board: guide capacity override removed (reset to default)
                BoardUpdated::dispatch($request->date, $request->time_from, $this->boardChannel());
                return response()->json([
                    'status'  => 'success',
                    'message' => 'Capacity matches default. Override removed.'
                ]);
            }

            // Requested ≠ default → update or insert
            if ($override) {

                // Update existing
                $override->update([
                    'capacity' => $requestedCapacity,
                ]);

            } else {

                // Insert new override
                GuideSpecificCapacity::create([
                    'guide_id'   => $request->guide_id,
                    // 'station_id'=> $request->station_id,
                    'time_from' => $request->time_from,
                    'date'      => $request->date,
                    'capacity'  => $requestedCapacity,
                ]);
            }

            // Notify board: guide capacity override saved
            BoardUpdated::dispatch($request->date, $request->time_from, $this->boardChannel());
            return response()->json([
                'status'  => 'success',
                'message' => 'Guide capacity updated successfully.'
            ]);

        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Bulk update assign guide capacity for specific date and time slot
     * @param Request $request
     */
    public function updateBulkCapacity(Request $request)
    {
        try {

            // Fetch all guide assignments for this slot
            $bookingTourGuides = BookingTourGuide::where('date', $request->date)
                ->where('time_from', $request->time_from)
                ->pluck('guide_id')
                ->unique()
                ->filter();

            if ($bookingTourGuides->isEmpty()) {
                return response()->json([
                    'status'  => 'warning',
                    'message' => 'No assigned guide found for this slot.',
                ]);
            }

            // Loop & call SINGLE update function
            foreach ($bookingTourGuides as $guideId) {

                // Create a fake request object
                $singleRequest = new \Illuminate\Http\Request([
                    'guide_id'  => $guideId,
                    'date'      => $request->date,
                    'time_from' => $request->time_from,
                    'capacity'  => $request->capacity,
                ]);

                // Call existing single update logic
                $this->updateGuideCapacity($singleRequest);
            }

            // Notify board: bulk guide capacity update completed
            BoardUpdated::dispatch($request->date, $request->time_from, $this->boardChannel());
            return response()->json([
                'status'  => 'success',
                'message' => 'Bulk capacity updated successfully.',
            ]);

        } catch (\Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    public function updateBookingChecked(Request $request)
    {
        try {
            $bookingTourGuide = BookingTourGuide::where('booking_id', $request->booking_id)
                                    ->where('station_id', $request->station_id)
                                    ->where('guide_id', $request->guide_id)
                                    ->where('time_from', $request->time_from)
                                    ->where('date', $request->date)
                                    ->first();

            if (!$bookingTourGuide) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking tour guide record not found.'
                ], 404);
            }
            $bookingTourGuide->booking_checked = $request->checked;
            $bookingTourGuide->save();

            // Notify board: booking checked status toggled
            BoardUpdated::dispatch($request->date, $request->time_from, $this->boardChannel());
            return response()->json([
                'success' => true,
                'message' => 'Booking checked.'
            ]);
        } catch (\Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Mark guide as available: Guide will be available for assignment
     * @param Request $request
     */
    public function markGuideAvailable(Request $request)
    {

        $guideId   = $request->guide_id;
        $date      = $request->currentDate;
        $timeFrom  = $request->currentTimeFrom;

        DB::beginTransaction();
        try {

            //  Check if guide is actually busy at this time
            $busyBlock = DB::table('guide_next_timeslot_block')
                ->where('guide_id', $guideId)
                ->where('date', $date)
                ->where('time_from', '<', $timeFrom)
                ->where('time_to', '>', $timeFrom)
                ->first();

            if (!$busyBlock) {
                return response()->json([
                    'status'  => 'warning',
                    'message' => 'Guide is already available'
                ], 404);
            }

            //  Remove block → guide becomes available
            DB::table('guide_next_timeslot_block')
                ->where('id', $busyBlock->id)
                ->delete();

            DB::commit();

            // Notify board: guide marked available (next-slot busy block removed)
            BoardUpdated::dispatch($date, $timeFrom, $this->boardChannel());
            return response()->json([
                'status'  => 'success',
                'message' => 'Guide marked as available successfully'
            ]);

        } catch (\Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Unlock station
     * @param Request $request
     */
    public function unlockStation(Request $request)
    {
        $stationId = $request->station_id;
        $date      = $request->date;
        $timeFrom  = $request->time_from;

        // Get Station Name
        $details = DB::table('booking_tour_guides as btg')
            ->join('stations as s', 's.id', '=', 'btg.station_id')

            ->leftJoin('board_station_specific_name as bssn', function ($join) use ($date) {
                $join->on('bssn.station_id', '=', 'btg.station_id')
                     ->on('bssn.time_from', '=', 'btg.time_from')
                     ->where('bssn.date', '=', $date);
            })

            ->where('btg.station_id', $stationId)
            ->where('btg.time_from', $timeFrom)
            ->whereDate('btg.date', $date)

            ->select(
                DB::raw("COALESCE(bssn.name, s.name) as station_name")
            )
            ->first();

        $stationName = $details->station_name ?? 'Station';

        // Get All Booking IDs
        $bookingIds = DB::table('booking_tour_guides')
            ->where('station_id', $stationId)
            ->whereDate('date', $date)
            ->where('time_from', $timeFrom)
            ->pluck('booking_id')
            ->unique()
            ->toArray();

        // Store History for Each Booking
        foreach ($bookingIds as $bookingId) {

            $note = "{$stationName} station has been successfully unlocked.";

            HistoryService::storeNoteInHistory($note, $bookingId);
        }

        // Update Check-in Status
        DB::table('booking_tour_guides')
            ->where('station_id', $stationId)
            ->whereDate('date', $date)
            ->where('time_from', $timeFrom)
            ->update([
                'check_in_status' => 'Pending',
                'updated_at'      => now()
            ]);

        // Notify board: station unlocked, check-in status reset to Pending
        BoardUpdated::dispatch($date, $timeFrom, $this->boardChannel());
        return response()->json([
            'success' => true,
            'message' => 'Station unlocked successfully.'
        ]);
    }

    private function boardChannel(): string
    {
        return (string) tenancy()->tenant->id;
    }
}
