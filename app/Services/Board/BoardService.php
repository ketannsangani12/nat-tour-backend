<?php

namespace App\Services\Board;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Station;
use App\Services\TimeSlot\TimeSlotService;
use App\Models\TourPackage;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\BookingTourGuide;
use App\Models\TourBooking;
use App\Models\Staff;
use Illuminate\Validation\ValidationException;
use App\Services\Booking\HistoryService;
use App\Events\Broadcast\Board\BoardUpdated;

class BoardService
{
    /**
     * Get board data (slots, stations, guides, totals) for a given date.
     */
    public function getBoardData(string $date): array
    {
        // 1. Fetch Slots
        $userType = userType();
        $slots = $this->getSlots($date);

        // 2. Fetch Stations
        $stations = Station::where(function ($q) use ($date) {
            $q->whereNull('station_date')
                ->orWhere('station_date', '0000-00-00')
                ->orWhere('station_date', $date);
        })
        ->orderByRaw('ISNULL(order_by), order_by ASC')
        ->get();

        $stations->transform(function ($station) {
            $packageIds = json_decode($station->tour_packages, true) ?? [];

            $station->packages = DB::table('tour_packages')
                ->whereIn('id', $packageIds)
                ->pluck('name as package_name')
                ->toArray();

            return $station;
        });

        // 3. Fetch Guide Assignments
        $bookingGuides = $this->getBookingGuides($date, $slots, $stations);

        // 4. Build Guide Data per Station
        $slotGuideCapacityMap = [];
        foreach ($stations as $station) {
            $station->setAttribute(
                'guides',
                $this->buildGuidesBySlot($station, $slots, $bookingGuides, $slotGuideCapacityMap)
            );
        }

        // 5. Compute Totals
        $this->computeSlotTotals($slots, $stations, $slotGuideCapacityMap);

        // 6. Dropdown Packages
        $tourPackages = $this->getTourPackages();

        return compact('slots', 'stations', 'tourPackages');
    }

    static function getSlots(string $date)
    {
        return DB::table('tour_packages as tp')
            ->join('tour_slot_times as tst', 'tst.tour_package_id', '=', 'tp.id')
            ->where('tst.deleted_by', 0)
            ->whereDate('tst.date', $date)
            ->where('tp.include_in_operation_board', 1)
            ->selectRaw('MIN(tst.id) as slot_id, tst.time_from')
            ->groupBy('tst.time_from')
            ->orderBy('tst.time_from')
            ->get()
            ->map(function ($row) use ($date) {
                return (object) [
                    'time_from'      => $row->time_from,
                    'formatted_time' => Carbon::parse($row->time_from)->format('h:i A'),
                    'id'             => $row->slot_id,
                    'date'           => $date,
                ];
            });
    }

    protected function getBookingGuides(string $date, $slots, $stations)
    {
        $slotTimes  = $slots->pluck('time_from')->toArray();
        $stationIds = $stations->pluck('id')->toArray();
        $userType   = userType();

        $query = DB::table('booking_tour_guides as btg')
            ->leftJoin('tour_operator_staff as tos', function ($join) {
                $join->on('tos.id', '=', 'btg.guide_id')
                     ->where('tos.status', 'Active');
            })
            ->leftJoin('tour_bookings_master as tbm', 'tbm.id', '=', 'btg.booking_id')

            ->leftJoin('guide_specific_capacity as gsc', function ($join) use ($date) {
                $join->on('gsc.guide_id', '=', 'btg.guide_id')
                     ->on('gsc.time_from', '=', 'btg.time_from')
                     ->where('gsc.date', '=', $date);
            })

            ->whereIn('btg.station_id', $stationIds)
            ->whereIn('btg.time_from', $slotTimes)
            ->where('btg.date', $date);

        // Tour Operator restriction
        if ($userType === 'Tour Operator') {
            $user = Staff::find(Auth::id());
            if ($user->can('guide-board')) {
                $query->where('tos.id', Auth::id());
            }
        }

        return $query->select(
                'btg.station_id',
                'btg.time_from',
                'btg.booking_id',
                'btg.guide_id',
                DB::raw("CONCAT(tos.first_name, ' ', tos.last_name) as full_name"),

                // effective capacity (override > default)
                DB::raw('COALESCE(gsc.capacity, tos.capacity) as capacity'),

                'tbm.tourists as total_booking_tourists',
                'btg.tourists as assigned_tourists'
            )
            ->get()
            ->groupBy(fn ($row) => $row->station_id . '|' . $row->time_from);
    }

    protected function buildGuidesBySlot($station, $slots, $bookingGuides, &$slotGuideCapacityMap)
    {
        $guidesBySlot = [];

        foreach ($slots as $slot) {
            $key     = $station->id.'|'.$slot->time_from;
            $entries = $bookingGuides[$key] ?? collect();

            $byGuide = $entries->groupBy('guide_id');

            $guideDetails = $byGuide->map(function ($rows, $guideId) use (&$slotGuideCapacityMap, $slot) {
                $first     = $rows->first();
                $gid       = (int) $guideId;
                $capacity  = (int) ($first->capacity ?? 0);
                $assigned  = $rows->sum('assigned_tourists');

                $totalBookingForGuide = $rows->pluck('booking_id')->unique()
                    ->map(fn($bid) => (int)$rows->firstWhere('booking_id', $bid)->total_booking_tourists)
                    ->sum();

                $slotGuideCapacityMap[$slot->time_from][$gid] = $capacity;

                return (object) [
                    'guide_id'          => $gid,
                    'guide_name'        => $first->full_name,
                    'capacity'          => $capacity,
                    'assigned_tourists' => (int) $assigned,
                    'total_booking'     => (int) $totalBookingForGuide,
                ];
            })->values();

            $bookedSeats = $guideDetails->sum('assigned_tourists');
            $totalNeeded = $entries->pluck('booking_id')->unique()
                ->map(fn($bid) => (int) $entries->firstWhere('booking_id', $bid)->total_booking_tourists)
                ->sum();

            $guidesBySlot[$slot->time_from] = (object) [
                'slot_id'      => $slot->id,
                'guides'       => $guideDetails,
                'booked_seats' => (int) $bookedSeats,
                'total_needed' => (int) $totalNeeded,
            ];
        }

        return $guidesBySlot;
    }

    protected function computeSlotTotals($slots, $stations, $slotGuideCapacityMap)
    {
        foreach ($slots as $slot) {
            $timeFrom = $slot->time_from;

            $slot->total_booked = $stations->sum(function ($s) use ($timeFrom) {
                return $s->guides[$timeFrom]->booked_seats ?? 0;
            });

            $slot->total_needed = $stations->sum(function ($s) use ($timeFrom) {
                return $s->guides[$timeFrom]->total_needed ?? 0;
            });

            $uniqueGuideCapacities = $slotGuideCapacityMap[$timeFrom] ?? [];
            $slot->total_capacity = array_sum($uniqueGuideCapacities);
        }
    }

    protected function getTourPackages()
    {
        return DB::table('tour_packages as tp')
            ->where('tp.include_in_operation_board', 1)
            ->get(['id', 'name']);
    }

    static function checkInStatus(object $booking)
    {
        // Fetch related tour booking transactions
        $tourBookingTransact = DB::table('tour_bookings_transact AS tbt')
            ->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
            ->where('tbt.tour_booking_id', $booking->id)
            ->where('tbt.deleted_by', 0)
            ->where('tbt.is_infant_rate', 0)
            ->get([
                'tbt.tourists',
                'tbt.check_in_status',
                'tpr.rate_for',
                'tbt.redeem_ticket',
                'tbt.tour_booking_id as bookingid'
            ])
            ->groupBy('bookingid');
        // Attach check-in status to the booking
        self::getCheckinsStatus($booking, $tourBookingTransact);

        return $booking;
    }

    static function checkInInfantsStatus(object $booking)
    {
        // Fetch related tour booking transactions
        $tourBookingTransact = DB::table('tour_bookings_transact AS tbt')
            ->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
            ->where('tbt.tour_booking_id', $booking->id)
            ->where('tbt.deleted_by', 0)
            ->where('tbt.is_infant_rate', 1)
            ->get([
                'tbt.tourists',
                'tbt.check_in_status',
                'tpr.rate_for',
                'tbt.redeem_ticket',
                'tbt.tour_booking_id as bookingid'
            ])
            ->groupBy('bookingid');
        // Attach infant check-in status using a separate property to avoid overwriting checkInsCount
        self::getCheckinsStatus($booking, $tourBookingTransact, 'checkInsInfantsCount');

        return $booking;
    }

    public static function getCheckinsStatus($item, $tourBookingTransact, $property = 'checkInsCount')
    {
        $item->$property = [
            '1' => 0, // 1st Check In
            '2' => 0, // 2nd Check In
            'A' => 0, // Absent
        ];

        if (isset($tourBookingTransact[$item->id])) {
            foreach ($tourBookingTransact[$item->id] as $transac) {
                self::checkIns($item, $transac, $property);
            }
        }
    }

    public static function checkIns($item, $transac, $property = 'checkInsCount')
    {
        // Decode the check-in status JSON string
        $checkIns = json_decode($transac->check_in_status);

        // Exit if not an array or empty
        if (!is_array($checkIns) || empty($checkIns)) {
            return;
        }

        // current counts or default
        $checkInsCount = $item->$property ?? ['1' => 0, '2' => 0, 'A' => 0];

        // Loop through each check-in entry and count statuses
        foreach ($checkIns as $checkIn) {
            if (!isset($checkIn->status)) {
                continue;
            }

            $status = $checkIn->status;

            // Only count known statuses
            if (in_array($status, ['1', '2', 'A'])) {
                $checkInsCount[$status]++;
            }
        }

        // Assign the updated count back to the item
        $item->$property = $checkInsCount;
    }

    public static function getAbsentCount($bookingId, $timeFrom, $date)
    {
        $booking = DB::table('tour_bookings_master as tbm')
            ->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->where('tbm.id', $bookingId)
            ->first(['tbm.id', 'tbm.tourists', 'tp.name as tour_package']);

        $checkInsResult = self::checkInStatus($booking);
        $checkInsCount  = $checkInsResult->checkInsCount ?? ['1' => 0, '2' => 0, 'A' => 0];

        return $checkInsCount['A'] ?? 0;
    }

    /**
     * Get only slot times for a given date + package.
     */
    public static function getOnlySlotTimesByDate(string $date, int $tourPackageId): array
    {
        try {
            // Ensure package exists
            $tourPackage = TourPackage::findOrFail($tourPackageId);
            $tourOperatorId = $tourPackage->tour_operator_id;

            // Update slot statuses for the date
            TimeSlotService::updateSlotStatus($date);

            // Build a proper Laravel Request object
            $slotRequest = new Request([
                'date'          => Carbon::parse($date)->format('Ymd'),
                'tourPackageId' => $tourPackageId,
                'getRateGroups' => 0,
            ]);

            // Fetch slot times
            $slotTimes = TimeSlotService::getTourSlotTimes(
                $slotRequest,
                $tourOperatorId,
                userType(),
                1, // showAllPackageBookings
                '' // no affiliate SQL
            );

            $slots = [];
            foreach ($slotTimes as $slot) {
                $slots[] = [
                    'id'        => $slot->id,
                    'time_from' => $slot->time_from,
                    'time_to'   => $slot->time_to,
                    'seats'     => $slot->seats,
                    'tourists'  => $slot->tourists,
                    'booked_seats' => $slot->tourists,
                    'bookable_status' => $slot->bookable_status,
                    'overbooked' => ($slot->tourists > $slot->seats)
                        ? abs($slot->tourists - $slot->seats)
                        : 0,
                ];
            }

            return [
                'slotTimes' => $slots,
                'tourPackageId' => $tourPackageId,
                'date' => $date,
            ];
        } catch (Exception $e) {
            Log::error($e);
            return ['slotTimes' => []];
        }
    }

    /**
     * Insert the data for block the next time slot for guide
     *
     * @param mixed $tourPackageId
     * @param mixed $date
     * @param mixed $timeFrom
     * @param mixed $guideId
     * @param mixed $stationId
     * @param mixed $bookingId
     * @return array
     */

    public static function blockNextTimeSlot($tourPackageId, $date, $timeFrom, $guideId, $stationId, $bookingId)
    {
        $useStation = !is_null($stationId);

        // Find slot covering requested time
        $nextSlot = DB::table('tour_slot_times')
            ->where('tour_package_id', $tourPackageId)
            ->where('date', $date)
            ->where('time_from', '<=', $timeFrom)
            ->where('time_to', '>', $timeFrom)
            ->orderByDesc('time_to')
            ->first();

        if (!$nextSlot) {
            return;
        }

        /* ----------------------------------------
         | TIME CALCULATION (UPDATED LOGIC)
         ----------------------------------------*/

        $requestFrom = Carbon::createFromFormat('H:i:s', $timeFrom);

        // slot duration
        $slotFrom = Carbon::createFromFormat('H:i:s', $nextSlot->time_from);
        $slotTo   = Carbon::createFromFormat('H:i:s', $nextSlot->time_to);
        $slotDurationMinutes = $slotFrom->diffInMinutes($slotTo);

        // NEW calculated window
        $originalFrom = $requestFrom->copy();
        $originalTo   = $requestFrom->copy()->addMinutes($slotDurationMinutes);

        // buffer
        $bufferedTo = $originalTo->copy()->addMinutes(30);

        // previous window
        $durationInMinutes = $originalFrom->diffInMinutes($bufferedTo);

        $previousTimeTo   = $originalFrom->format('H:i:s');
        $previousTimeFrom = $originalFrom
            ->copy()
            ->subMinutes($durationInMinutes)
            ->format('H:i:s');

        $timeFromFinal = $originalFrom->format('H:i:s');
        $timeToFinal   = $bufferedTo->format('H:i:s');

        /* ----------------------------------------
         | STEP 1: Update orphan booking
         ----------------------------------------*/
        if (!empty($bookingId)) {
            $existing = DB::table('guide_next_timeslot_block')
                ->where('booking_id', $bookingId)
                ->whereNull('station_id')
                ->first();

            if ($existing) {
                DB::table('guide_next_timeslot_block')
                    ->where('id', $existing->id)
                    ->update([
                        'station_id'         => $stationId,
                        'guide_id'           => $guideId,
                        'date'               => $date,
                        'time_from'          => $timeFromFinal,
                        'time_to'            => $timeToFinal,
                        'previous_time_from' => $previousTimeFrom,
                        'previous_time_to'   => $previousTimeTo,
                        'updated_at'         => now(),
                    ]);
                return;
            }
        }

        /* ----------------------------------------
         | STEP 2: Insert / Update
         ----------------------------------------*/
        $query = DB::table('guide_next_timeslot_block')
            ->where('guide_id', $guideId)
            ->where('date', $date)
            ->where('time_from', $timeFromFinal);

        $useStation
            ? $query->where('station_id', $stationId)
            : $query->where('booking_id', $bookingId);

        $query->delete();

        $data = [
            'guide_id'           => $guideId,
            'date'               => $date,
            'time_from'          => $timeFromFinal,
            'time_to'            => $timeToFinal,
            'previous_time_from' => $previousTimeFrom,
            'previous_time_to'   => $previousTimeTo,
            'created_at'         => now(),
            'updated_at'         => now(),
        ];

        if ($useStation) {
            $data['station_id'] = $stationId;
            $uniqueKeys = [
                'date'       => $date,
                'time_from'  => $timeFromFinal,
                'station_id' => $stationId,
            ];
        } else {
            $data['booking_id'] = $bookingId;
            $uniqueKeys = [
                'date'       => $date,
                'time_from'  => $timeFromFinal,
                'booking_id' => $bookingId,
            ];
        }

        DB::table('guide_next_timeslot_block')->updateOrInsert($uniqueKeys, $data);
    }

     /**
     * Delete the record of block the next time slot for guide
     *
     * @param mixed $tourPackageId
     * @param mixed $date
     * @param mixed $timeFrom
     * @param mixed $guideId
     * @param mixed $stationId
     * @return array
     */

    public static function deleteBlockNextTimeSlot($date, $guideId,$timeFrom, $stationId, $bookingId)
    {
        // Determine whether to use station_id or booking_id
        $useStation = !is_null($stationId);

        $query = DB::table('guide_next_timeslot_block')
            ->where('guide_id', $guideId)
            ->where('time_from', $timeFrom)
            ->where('date', $date);

        // Apply the correct condition
        if ($useStation) {
            $query->where('station_id', $stationId);
        } else {
            $query->where('booking_id', $bookingId);
        }

        // Check if record exists
        if ($query->exists()) {
            $query->delete();
        }
    }

    public static function boardBookingUnassign($bookingId, $date = null, $timeFrom = null)
    {
        $tourGuides = DB::table('booking_tour_guides as btg')
                ->join('tour_bookings_master as tbm', 'tbm.id', '=', 'btg.booking_id')
                ->join('stations as s', 's.id', '=', 'btg.station_id')
                ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
                ->join('tour_operator_staff as tos', 'tos.id', '=', 'btg.guide_id')
                ->where('btg.booking_id', $bookingId)
                ->get(['tst.time_from', 'tst.date', 'btg.id','btg.booking_id','btg.tourists','s.tour_packages', DB::raw("CONCAT(tos.first_name, ' ', tos.last_name) AS guideName")]);
        
            if (count($tourGuides) > 0) {
                DB::table('booking_tour_guides')
                ->where('booking_id', $bookingId)
                ->update([
                    'booking_id' => null,
                    'tourists'   => 0,
                    'infants' => 0
                ]);

                $note = "Tour guide " .$tourGuides[0]->guideName . " has been successfully removed from the booking.";
                HistoryService::storeNoteInHistory($note, $bookingId);
            }
        
            // Notify board: unassign the booking
            if (count($tourGuides) > 0) {
                if(empty($date)) {
                    $date = $tourGuides[0]->date;
                    $timeFrom = $tourGuides[0]->time_from;
                }
                BoardUpdated::dispatch($date, $timeFrom, (string) tenancy()->tenant->id);
            }
        
    }

    public static function boardBookingUpdate($bookingId)
    {
        $assignedCount = BookingTourGuide::where('booking_id', $bookingId)->count();

        $data = DB::table('tour_bookings_master as tbm')
            ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->where('tbm.id', $bookingId)
            ->first(['tst.time_from', 'tst.date']);
        
        if ($assignedCount> 0) {
            if ($assignedCount > 1) {
                self::boardBookingUnassign($bookingId);
            } else {
                $tourBooking = TourBooking::find($bookingId);
                $tourGuides = BookingTourGuide::where('booking_id', $tourBooking->id)->first();
                $tourists = 0;

                // Absent pax count
                $checkInsResult = self::checkInStatus($tourBooking);
                $tourBooking->checkInsCount = $checkInsResult->checkInsCount ?? ['1' => 0, '2' => 0, 'A' => 0];
                $absentCount   = $tourBooking->checkInsCount['A'] ?? 0;
                $tourists = max(0, $tourBooking->tourists - $absentCount);

                $tourGuides->tourists = $tourists;

                // Infants absent pax count
                $infants = 0;
                $checkInsInfantsResult = self::checkInInfantsStatus($tourBooking);
                $tourBooking->checkInsInfantsCount = $checkInsInfantsResult->checkInsInfantsCount ?? ['1' => 0, '2' => 0, 'A' => 0];
                $absentInfantsCount = $tourBooking->checkInsInfantsCount['A'] ?? 0;
                $infants = max(0, $tourBooking->infants - $absentInfantsCount);

                $tourGuides->infants = $infants;
                $tourGuides->save();
                // Dispatch after the caller's transaction commits so the board
                // reads already-persisted data.
                $date     = $data->date;
                $timeFrom = $data->time_from;
                $tenantId = (string) tenancy()->tenant->id;
                DB::afterCommit(function () use ($date, $timeFrom, $tenantId) {
                    BoardUpdated::dispatch($date, $timeFrom, $tenantId);
                });
                if ($tourists == 0) {
                    self::boardBookingUnassign($bookingId);
                }
            }
        }
        
    }

    public static function getAvailableGuides($request)
    {
        $guidesQuery = DB::table('tour_operator_staff AS tos')
            ->join('model_has_roles AS mhr', 'mhr.model_id', '=', 'tos.id')
            ->join('roles AS r', 'r.id', '=', 'mhr.role_id')

            // JOIN guide specific capacity (override)
            ->leftJoin('guide_specific_capacity AS gsc', function ($join) use ($request) {
                $join->on('gsc.guide_id', '=', 'tos.id')
                     ->where('gsc.date', '=', $request->date)
                     ->where('gsc.time_from', '=', $request->time_from);
            })
            ->where('r.name', 'tour-guide')
            ->where('tos.status', 'Active')
            ->where('tos.guide_status', 'Active');

        // Exclude guides who are busy at the requested time
        $guidesQuery->whereNotExists(function ($query) use ($request) {
            $query->select(DB::raw(1))
                ->from('guide_next_timeslot_block AS gntb')
                ->whereColumn('gntb.guide_id', 'tos.id')
                ->where('gntb.date', $request->date)
                ->where(function ($q) use ($request) {

                    // Forward slot overlap
                    $q->where(function ($f) use ($request) {
                        $f->where('gntb.time_from', '<', $request->time_from)
                          ->where('gntb.time_to',   '>', $request->time_from);
                    })

                    // OR backward (previous) slot overlap
                   /* ->orWhere(function ($p) use ($request) {
                        $p->where('gntb.previous_time_from', '<', $request->time_from)
                          ->where('gntb.previous_time_to',   '>', $request->time_from);
                    })*/;

                });
        });

        // Final select
        return $guidesQuery->get([
            'tos.id',
            DB::raw("CONCAT(tos.first_name, ' ', tos.last_name) AS guideName"),

            // effective capacity
            DB::raw('COALESCE(gsc.capacity, tos.capacity) AS capacity'),
        ]);
    }

    public static function getNotAvailableGuides($request)
    {
        $guidesQuery = DB::table('tour_operator_staff AS tos')
            ->join('model_has_roles AS mhr', 'mhr.model_id', '=', 'tos.id')
            ->join('roles AS r', 'r.id', '=', 'mhr.role_id')

            // JOIN guide specific capacity (override)
            ->leftJoin('guide_specific_capacity AS gsc', function ($join) use ($request) {
                $join->on('gsc.guide_id', '=', 'tos.id')
                     ->where('gsc.date', '=', $request->date)
                     ->where('gsc.time_from', '=', $request->time_from);
            })
            ->where('r.name', 'tour-guide')
            ->where('tos.status', 'Active')
            ->where('tos.guide_status', 'Active');

        // ONLY guides who are busy at requested time
        $guidesQuery->whereExists(function ($query) use ($request) {
            $query->select(DB::raw(1))
                ->from('guide_next_timeslot_block AS gntb')
                ->whereColumn('gntb.guide_id', 'tos.id')
                ->where('gntb.date', $request->date)
                ->where(function ($q) use ($request) {
                    $q->where('gntb.time_from', '<', $request->time_from)
                      ->where('gntb.time_to',   '>', $request->time_from);
                });
        });

        return $guidesQuery->get([
            'tos.id',
            DB::raw("CONCAT(tos.first_name, ' ', tos.last_name) AS guideName"),
            DB::raw('COALESCE(gsc.capacity, tos.capacity) AS capacity'),
        ]);
    }

    public static function validateGuideAssignment(Request $request)
    {
        $bookingId = $request->booking_id;
        $date      = $request->date;
        $timeFrom  = $request->time_from;
        $guides    = $request->guides ?? [];

        $booking = DB::table('tour_bookings_master')
            ->where('id', $bookingId)
            ->select('id', 'tourists')
            ->first();

        if (!$booking) {
            throw new Exception('Booking not found.');
        }

        $totalTourists = (int) $booking->tourists;

        // Tourists already assigned (ALL)
        $alreadyAssigned = (int) DB::table('booking_tour_guides')
            ->where('booking_id', $bookingId)
            ->where('date', $date)
            ->where('time_from', $timeFrom)
            ->sum('tourists');

        
        // Guide IDs coming from request
        $requestGuideIds = collect($guides)
            ->pluck('guide_id')
            ->filter()
            ->unique()
            ->toArray();

         // Tourists already assigned to THESE guides
         // (we subtract them because they’re being updated)
        $currentGuideAssigned = (int) DB::table('booking_tour_guides')
            ->where('booking_id', $bookingId)
            ->where('date', $date)
            ->where('time_from', $timeFrom)
            ->whereIn('guide_id', $requestGuideIds)
            ->sum('tourists');
       
         // Effective assigned (others only)
        $effectiveAssigned = $alreadyAssigned - $currentGuideAssigned;

       
        // Requested tourists (new values)
        $requestedTourists = collect($guides)
            ->sum(fn ($g) => (int) ($g['tourists'] ?? 0));

     
        // validation
        if (($effectiveAssigned + $requestedTourists) > $totalTourists) {
            $pending = $totalTourists - $effectiveAssigned;

            throw ValidationException::withMessages([
                'tourists' => [
                    "Cannot assign {$requestedTourists} tourists. Only {$pending} tourists available. This booking may already be partially assigned. Please reload the page."
                ]
            ]);
        }

        //  passes
    }

}


