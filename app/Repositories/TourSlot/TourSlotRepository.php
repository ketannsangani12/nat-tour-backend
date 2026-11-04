<?php

namespace App\Repositories\TourSlot;

use App\Constants\Constants;
use App\Models\Customer;
use App\Models\TourPackageRate;
use App\Models\TourOperator;
use App\Models\TourSlotTime;
use App\Repositories\Repository;
use App\Exceptions\Core\GeneralCoreException;
use App\Jobs\ApplyBulkOperationJob;
use App\Jobs\ClosureEmails;
use App\Jobs\GenerateBulkSlots;
use App\Models\Hotel;
use App\Models\RateSlotMapping;
use App\Models\SurchargeDate;
use App\Models\TourDateSlot;
use App\Models\TourPackage;
use App\Models\TourPackageFamily;
use App\Models\TourSlotBulkOperation;
use App\Services\Booking\BookingService;
use App\Services\Booking\RecordsHistoryService;
use App\Services\Calendar\CalendarService;
use App\Services\Cart\CartService;
use App\Services\TimeSlot\TimeSlotService;
use App\Services\TimeSlot\TouristService;
use App\Services\TipCenter\TipCenterService;
use App\Services\TourPackageRate\TourPackageRateService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cookie;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class TourSlotRepository extends Repository
{
    /**
     * @var string
     */
    public $customer = Customer::class;

    public $tourPackage = TourPackage::class;

    public $tourPackageRate = TourPackageRate::class;

    public $tourOperator = TourOperator::class;

    public $hotel = Hotel::class;

    public $customRateDate = SurchargeDate::class;

    public $rateSlotMapping = RateSlotMapping::class;

    /**
     * Distinct slot "from times" (formatted "g:i A") for a package within a date range.
     * Scoped to the current user's department packages. Used by the Bulk Manager
     * "Select Existing" grid.
     *
     * @return array<int, string>
     */
    public function getDistinctSlotTimes(int $tourPackageId, string $fromDate, string $toDate): array
    {
        $departmentPackage = departmentPackage();
        if (!empty($departmentPackage) && !in_array($tourPackageId, $departmentPackage)) {
            return [];
        }

        return TourSlotTime::query()
            ->where('tour_package_id', $tourPackageId)
            ->where('deleted_by', Constants::STATUS_ZERO)
            ->whereBetween('date', [$fromDate, $toDate])
            ->whereNotNull('time_from')
            ->distinct()
            ->orderBy('time_from')
            ->pluck('time_from')
            ->map(fn ($time) => $this->formatSlotTime($time))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * All existing slot rows for a package within a date range. Each row carries the
     * slot time id (slot_id), parent tour_slot_id, date, formatted from/to times, seats,
     * bookable status and hidden flag. Drives the Bulk Manager preview (per date + slot).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSlotsInRange(int $tourPackageId, string $fromDate, string $toDate): array
    {
        $departmentPackage = departmentPackage();
        if (!empty($departmentPackage) && !in_array($tourPackageId, $departmentPackage)) {
            return [];
        }

        // Booked tourists are counted from tour_bookings_master (excluding cancelled) — the
        // tour_slot_summaries cache is unreliable for older bookings.
        $slots = DB::table('tour_slot_times as tst')
            ->leftJoin('tour_bookings_master as tbm', function ($join) {
                $join->on('tbm.tour_slot_id', '=', 'tst.id')
                     ->whereColumn('tbm.tour_package_id', '=', 'tst.tour_package_id')
                     ->where('tbm.status', '!=', 'Cancelled');
            })
            ->where('tst.tour_package_id', $tourPackageId)
            ->where('tst.deleted_by', Constants::STATUS_ZERO)
            ->whereBetween('tst.date', [$fromDate, $toDate])
            ->whereNotNull('tst.time_from')
            ->groupBy(
                'tst.id', 'tst.tour_slot_id', 'tst.tour_package_id', 'tst.date', 'tst.time_from',
                'tst.time_to', 'tst.time_slot_seats', 'tst.bookable_status', 'tst.hide_slot'
            )
            ->orderBy('tst.date')
            ->orderBy('tst.time_from')
            ->orderBy('tst.id') // if duplicate rows exist for a date+time, the latest (current) row wins in the index
            ->get([
                'tst.id', 'tst.tour_slot_id', 'tst.tour_package_id', 'tst.date', 'tst.time_from',
                'tst.time_to', 'tst.time_slot_seats', 'tst.bookable_status', 'tst.hide_slot',
                DB::raw('COALESCE(SUM(tbm.tourists), 0) AS booked_tourists'),
                DB::raw('COUNT(tbm.id) AS bookings_count'),
            ]);

        // ── Package-relation (family) seat sharing — mirrors the calendar's calculation. ──
        $groupedByYear      = []; // year => relations grouped by group_index
        $currentGroupByYear = []; // year => [ slot time_from => group_index ] for the selected package
        $allSlotData        = collect(); // "{pkg}|{date}|{time_from}" => [(object){ total_seats, total_tourists }]

        if (hasSetting('PACKAGE_RELATION') && $slots->isNotEmpty()) {
            $years = $slots->map(fn ($s) => substr((string) $s->date, 0, 4))->unique()->values();
            $relatedPackageIds = collect([$tourPackageId]);

            foreach ($years as $year) {
                $relatedPackages = DB::table('tour_package_families')
                    ->where('relation_year', $year)
                    ->whereIn('parent_package_id', function ($q) use ($tourPackageId, $year) {
                        $q->select('parent_package_id')->from('tour_package_families')
                          ->where('tour_package_id', $tourPackageId)->where('relation_year', $year);
                    })
                    ->get();

                if ($relatedPackages->isEmpty()) {
                    continue;
                }
                $groupedByYear[$year]      = $relatedPackages->groupBy('group_index');
                $currentGroupByYear[$year] = $relatedPackages->where('tour_package_id', $tourPackageId)
                    ->pluck('group_index', 'time_from')->toArray();
                $relatedPackageIds = $relatedPackageIds->merge($relatedPackages->pluck('tour_package_id'));
            }

            $relatedPackageIds = $relatedPackageIds->unique()->values();
            if ($relatedPackageIds->count() > 1) {
                // Seats + booked tourists for every related package's slots across the range.
                $relRows = DB::table('tour_slot_times as tst')
                    ->leftJoin('tour_bookings_master as tbm', function ($join) {
                        $join->on('tbm.tour_slot_id', '=', 'tst.id')
                             ->whereColumn('tbm.tour_package_id', '=', 'tst.tour_package_id')
                             ->where('tbm.status', '!=', 'Cancelled');
                    })
                    ->whereIn('tst.tour_package_id', $relatedPackageIds->all())
                    ->where('tst.deleted_by', Constants::STATUS_ZERO)
                    ->whereBetween('tst.date', [$fromDate, $toDate])
                    ->whereNotNull('tst.time_from')
                    ->groupBy('tst.tour_package_id', 'tst.date', 'tst.time_from', 'tst.time_slot_seats')
                    ->get([
                        'tst.tour_package_id', 'tst.date', 'tst.time_from',
                        DB::raw('COALESCE(tst.time_slot_seats, 0) AS total_seats'),
                        DB::raw('COALESCE(SUM(tbm.tourists), 0) AS total_tourists'),
                    ]);

                foreach ($relRows as $rs) {
                    $allSlotData->put("{$rs->tour_package_id}|{$rs->date}|{$rs->time_from}", [(object) [
                        'date'            => $rs->date,
                        'time_from'       => $rs->time_from,
                        'tour_package_id' => $rs->tour_package_id,
                        'total_seats'     => (int) $rs->total_seats,
                        'total_tourists'  => (int) $rs->total_tourists,
                    ]]);
                }
            }
        }

        return $slots->map(function ($r) use ($groupedByYear, $currentGroupByYear, $allSlotData) {
            $seats     = (int) $r->time_slot_seats;
            $booked    = (int) $r->booked_tourists;
            $available = max(0, $seats - $booked);

            // When this slot is part of a relation group, recompute booked/available with the
            // parent-child rules (parent shares its seats with children; children are capped by both).
            $year = substr((string) $r->date, 0, 4);
            if (isset($groupedByYear[$year], $currentGroupByYear[$year][$r->time_from]) && $allSlotData->isNotEmpty()) {
                $relations = $groupedByYear[$year]->get($currentGroupByYear[$year][$r->time_from]);
                if ($relations && $relations->isNotEmpty()) {
                    $availability = TouristService::calculateAvailabilityOptimized($relations, $allSlotData, $r);
                    $available    = (int) $availability['available_seats'];
                    $booked       = (int) $availability['tourists_shown'];
                }
            }

            // Fully booked (no seats left) → effectively Closed, overriding the stored status.
            $isFull = $seats > 0 && $available <= 0;
            return [
                'id'         => (int) $r->id,
                'tourSlotId' => (int) $r->tour_slot_id,
                'date'       => Carbon::parse($r->date)->format('Y-m-d'),
                'from'       => $this->formatSlotTime($r->time_from),
                'to'         => $r->time_to ? $this->formatSlotTime($r->time_to) : null,
                'seats'      => $seats,
                'booked'     => $booked,
                'bookings'   => (int) $r->bookings_count,
                'available'  => $available,
                'status'     => $isFull ? 'Closed' : $r->bookable_status,
                'rawStatus'  => $r->bookable_status,
                'hide'       => (bool) $r->hide_slot,
            ];
        })->all();
    }

    /**
     * Format a stored slot time ("H:i:s") for display ("g:i A").
     */
    private function formatSlotTime($time): string
    {
        try {
            return Carbon::createFromFormat('H:i:s', $time)->format('g:i A');
        } catch (Exception $e) {
            return Carbon::parse($time)->format('g:i A');
        }
    }

    /**
     * Persist a queued Bulk Manager operation to tour_slot_bulk_operations.
     *
     * @return array<string, mixed>
     */
    public function storeBulkOperation(object $request): array
    {
        // Update Status / Seats / Convert / Visibility require a package, date range and slots.
        if ($invalid = $this->validateQueueSelection($request)) {
            return $invalid;
        }

        // Create-slots: reject if any (date + time) combination already exists as an active slot.
        if ($request->action === 'create') {
            $duplicates = $this->findExistingCreateSlots($request);
            if (!empty($duplicates)) {
                return [
                    'success'    => false,
                    'code'       => 'duplicate_slots',
                    'title'      => 'Duplicate slots',
                    'message'    => $this->buildDuplicateSlotsMessage($duplicates),
                    'duplicates' => $duplicates,
                ];
            }
        }

        // Convert: reject if any conversion targets a time that already has a slot (it would update it).
        if ($request->action === 'convert') {
            $conflicts = $this->findConvertTargetConflicts($request);
            if (!empty($conflicts)) {
                return [
                    'success'   => false,
                    'code'      => 'convert_target_exists',
                    'title'     => 'Slot already exists',
                    'message'   => $this->buildConvertConflictMessage($conflicts),
                    'conflicts' => $conflicts,
                ];
            }
        }

        // Stamp the creator's name + role into the payload ("Name: (Role)") for the Change History.
        $payload = (array) ($request->payload ?? []);
        $payload['created_by_with_name'] = BookingService::createdUserInfo();

        $operation = TourSlotBulkOperation::create([
            'tour_package_id'      => $request->tour_package_id,
            'action'               => $request->action,
            'from_date'            => $request->from_date,
            'to_date'              => $request->to_date,
            'slots'                => $request->slots ?? [],
            'payload'              => $payload,
            'total_slots'          => $request->total_slots ?? 0,
            'status'               => 'queued',
            'created_by'           => Auth::id(),
            'created_by_user_type' => userType(),
        ]);

        return ['success' => true, 'id' => $operation->id];
    }

    /**
     * Queued Bulk Manager operations for the current user's department packages —
     * used to repopulate the queue when the page is reloaded.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getQueuedBulkOperations(): array
    {
        $departmentPackage = departmentPackage();

        return TourSlotBulkOperation::query()
            ->with('tourPackage:id,name')
            ->where('status', 'queued')
            ->where('deleted_by', Constants::STATUS_ZERO)
            // Only the current user's own operations — the queue is private per user.
            ->where('created_by', Auth::id())
            ->where('created_by_user_type', userType())
            ->when(!empty($departmentPackage), fn ($q) => $q->whereIn('tour_package_id', $departmentPackage))
            ->orderBy('id')
            ->get()
            ->map(fn ($op) => [
                'id'              => (int) $op->id,
                'tour_package_id' => $op->tour_package_id,
                'package_name'    => optional($op->tourPackage)->name,
                'action'          => $op->action,
                'from_date'       => $op->from_date?->format('Y-m-d'),
                'to_date'         => $op->to_date?->format('Y-m-d'),
                'slots'           => $op->slots ?? [],
                'payload'         => $op->payload ?? [],
                'total_slots'     => (int) $op->total_slots,
            ])
            ->all();
    }

    /**
     * Applied Bulk Manager operations for the current user's department packages — shown in the
     * Change History section (most recently applied first).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAppliedBulkOperations(): array
    {
        $departmentPackage = departmentPackage();

        return TourSlotBulkOperation::query()
            ->with('tourPackage:id,name')
            // Show operations that have left the queue — processing / applied / failed. Still-queued
            // ops live in the Queue panel, and soft-deleted ops are excluded. Newest first.
            ->whereIn('status', ['processing', 'applied', 'failed'])
            ->where('deleted_by', Constants::STATUS_ZERO)
            // Only the current user's own history — Change History is private per user.
            ->where('created_by', Auth::id())
            ->where('created_by_user_type', userType())
            ->when(!empty($departmentPackage), fn ($q) => $q->whereIn('tour_package_id', $departmentPackage))
            ->orderByDesc('id')
            ->limit(100) // cap the history list
            ->get()
            ->map(function ($op) {
                // Prefer the stored "Name: (Role)" label; fall back to the actor type for legacy rows.
                $createdByWithName = ((array) ($op->payload ?? []))['created_by_with_name'] ?? null;
                if ($createdByWithName) {
                    $parsed    = parseCreatedByWithName((string) $createdByWithName);
                    $appliedBy = trim($parsed['name'] . ($parsed['role'] ? ' (' . $parsed['role'] . ')' : ''));
                } else {
                    $appliedBy = $op->created_by_user_type;
                }

                return [
                    'id'              => (int) $op->id,
                    'tour_package_id' => $op->tour_package_id,
                    'package_name'    => optional($op->tourPackage)->name,
                    'action'          => $op->action,
                    'status'          => $op->status, // queued / processing / applied / failed
                    'from_date'       => $op->from_date?->format('Y-m-d'),
                    'to_date'         => $op->to_date?->format('Y-m-d'),
                    'slots'           => $op->slots ?? [],
                    'payload'         => $op->payload ?? [],
                    'total_slots'     => (int) $op->total_slots,
                    'applied_at'      => $op->applied_at?->format('Y-m-d H:i:s'),
                    'applied_by'      => $appliedBy, // "Name (Role)" of who created the operation
                ];
            })
            ->all();
    }

    /**
     * Clear the Change History — soft-delete all applied Bulk Manager operations for the current
     * user's department packages (sets deleted_at + deleted_by, matching the platform convention).
     *
     * @return array<string, mixed>
     */
    public function clearAppliedBulkOperations(): array
    {
        $departmentPackage = departmentPackage();

        $count = TourSlotBulkOperation::query()
            ->where('status', 'applied')
            ->where('deleted_by', Constants::STATUS_ZERO)
            // Clear only the current user's own history.
            ->where('created_by', Auth::id())
            ->where('created_by_user_type', userType())
            ->when(!empty($departmentPackage), fn ($q) => $q->whereIn('tour_package_id', $departmentPackage))
            ->update([
                'deleted_at' => now(),
                'deleted_by' => Auth::id() ?? 0,
            ]);

        return ['success' => true, 'cleared' => $count];
    }

    /**
     * Soft-delete a queued Bulk Manager operation (scoped to the user's department packages).
     *
     * @return array<string, mixed>
     */
    public function deleteBulkOperation(int $id): array
    {
        $departmentPackage = departmentPackage();

        $operation = TourSlotBulkOperation::query()
            ->where('id', $id)
            ->where('deleted_by', Constants::STATUS_ZERO)
            // Only allow acting on the current user's own operation.
            ->where('created_by', Auth::id())
            ->where('created_by_user_type', userType())
            ->when(!empty($departmentPackage), fn ($q) => $q->whereIn('tour_package_id', $departmentPackage))
            ->first();

        if (!$operation) {
            return ['success' => false, 'message' => 'Operation not found.'];
        }

        $operation->delete(); // soft delete — SoftDeletes sets deleted_at, the model hook sets deleted_by

        return ['success' => true];
    }

    /**
     * Update an existing queued Bulk Manager operation (used when a queued op is edited).
     *
     * @return array<string, mixed>
     */
    public function updateBulkOperation(int $id, object $request): array
    {
        $departmentPackage = departmentPackage();

        $operation = TourSlotBulkOperation::query()
            ->where('id', $id)
            ->where('deleted_by', Constants::STATUS_ZERO)
            // Only allow acting on the current user's own operation.
            ->where('created_by', Auth::id())
            ->where('created_by_user_type', userType())
            ->when(!empty($departmentPackage), fn ($q) => $q->whereIn('tour_package_id', $departmentPackage))
            ->first();

        if (!$operation) {
            return ['success' => false, 'message' => 'Operation not found.'];
        }

        // Update Status / Seats / Convert / Visibility require a package, date range and slots.
        if ($invalid = $this->validateQueueSelection($request)) {
            return $invalid;
        }

        // Create-slots: reject if any (date + time) combination already exists as an active slot.
        if ($request->action === 'create') {
            $duplicates = $this->findExistingCreateSlots($request);
            if (!empty($duplicates)) {
                return [
                    'success'    => false,
                    'code'       => 'duplicate_slots',
                    'title'      => 'Duplicate slots',
                    'message'    => $this->buildDuplicateSlotsMessage($duplicates),
                    'duplicates' => $duplicates,
                ];
            }
        }

        // Convert: reject if any conversion targets a time that already has a slot (it would update it).
        if ($request->action === 'convert') {
            $conflicts = $this->findConvertTargetConflicts($request);
            if (!empty($conflicts)) {
                return [
                    'success'   => false,
                    'code'      => 'convert_target_exists',
                    'title'     => 'Slot already exists',
                    'message'   => $this->buildConvertConflictMessage($conflicts),
                    'conflicts' => $conflicts,
                ];
            }
        }

        // Keep the original creator's name + role (the request payload doesn't round-trip it).
        $payload = (array) ($request->payload ?? []);
        $existingPayload = (array) ($operation->payload ?? []);
        $payload['created_by_with_name'] = $existingPayload['created_by_with_name'] ?? BookingService::createdUserInfo();

        $operation->update([
            'tour_package_id'      => $request->tour_package_id,
            'action'               => $request->action,
            'from_date'            => $request->from_date,
            'to_date'              => $request->to_date,
            'slots'                => $request->slots ?? [],
            'payload'              => $payload,
            'total_slots'          => $request->total_slots ?? 0,
            'status'               => 'queued',
            'updated_by'           => Auth::id(),
            'updated_by_user_type' => userType(),
        ]);

        return ['success' => true, 'id' => $operation->id];
    }

    /**
     * Apply every queued Bulk Manager operation. The operations are handed to a single self-chaining
     * job that applies them strictly one-by-one (op N+1 only starts after op N finishes) and always
     * continues to the next op even if one fails.
     *
     * To keep it idempotent and prevent a second "Apply All" from re-dispatching the same rows, the
     * selected operations are claimed (status → 'processing') before dispatch, inside a cache lock so
     * two concurrent requests can't claim the same rows.
     *
     * @return array<string, mixed>
     */
    public function applyQueuedBulkOperations(): array
    {
        $departmentPackage = departmentPackage();

        // Cache is tenant-scoped (CacheTenancyBootstrapper), so this lock is per-tenant.
        $lock = Cache::lock('tour_slot_bulk_apply', 60);
        if (!$lock->get()) {
            return ['success' => false, 'message' => 'These operations are already being applied. Please wait a moment.'];
        }

        try {
            $ids = TourSlotBulkOperation::query()
                ->where('status', 'queued')
                ->where('deleted_by', Constants::STATUS_ZERO)
                // Apply only the current user's own queued operations.
                ->where('created_by', Auth::id())
                ->where('created_by_user_type', userType())
                ->when(!empty($departmentPackage), fn ($q) => $q->whereIn('tour_package_id', $departmentPackage))
                ->orderBy('id') // queue order = insertion order
                ->pluck('id')
                ->all();

            if (empty($ids)) {
                return ['success' => false, 'message' => 'There are no queued operations to apply.'];
            }

            // Claim them so a second apply (or page reload) won't pick the same rows up again.
            TourSlotBulkOperation::whereIn('id', $ids)->update(['status' => 'processing']);

            // Self-chaining job: applies the operations strictly in order, and always moves on to the
            // next op even if one fails. (Bus::chain stops the whole chain on the first failure, so it
            // can't satisfy "Job 2 must run even if Job 1 fails" — hence a self-chaining job instead.)
            ApplyBulkOperationJob::dispatch($ids);

            return ['success' => true, 'dispatched' => count($ids)];
        } finally {
            $lock->release();
        }
    }

    /**
     * Backend guard for adding/updating an existing-slot operation (status / seats / convert /
     * visibility): a tour package, a date range and at least one selected slot are all required.
     * Returns a `success:false` payload (so the UI shows the message + rolls back) or null when valid.
     *
     * @return array<string, mixed>|null
     */
    private function validateQueueSelection(object $request): ?array
    {
        if (!in_array($request->action, ['status', 'seats', 'convert', 'visibility'], true)) {
            return null; // create has its own validation; other actions aren't selection-based
        }

        $fail = fn (string $message) => ['success' => false, 'title' => 'Cannot add to queue', 'message' => $message];

        if (empty($request->tour_package_id)) {
            return $fail('Please select a tour package before adding to the queue.');
        }
        if (empty($request->from_date) || empty($request->to_date)) {
            return $fail('Please select a date range before adding to the queue.');
        }
        $slots = $request->slots ?? [];
        if (!is_array($slots) || count($slots) === 0) {
            return $fail('Please select at least one time slot before adding to the queue.');
        }

        return null;
    }

    /**
     * For a "create" bulk operation, find every (date + time) combination it would create that
     * already exists as an active tour_slot_times row for the package.
     *
     * @return array<int, array{date: string, time: string}>  e.g. [['date' => '2026-06-25', 'time' => '7:00 AM']]
     */
    private function findExistingCreateSlots(object $request): array
    {
        $payload    = (array) ($request->payload ?? []);
        $createRows = $payload['createRows'] ?? [];
        $days       = array_map('intval', $payload['days'] ?? []); // 0 (Sun) .. 6 (Sat), matching the UI

        if (empty($createRows)) {
            return [];
        }

        $tourPackageId = (int) $request->tour_package_id;
        $fromDate      = $request->from_date;
        $toDate        = $request->to_date;

        // Each create row → its 24h time key (for matching) + 12h label (for the message).
        $rowTimes = [];
        foreach ($createRows as $row) {
            $period = strtoupper($row['fromPeriod'] ?? 'AM');
            $label  = ($row['fromHour'] ?? '') . ':' . ($row['fromMinute'] ?? '00') . ' ' . $period;
            $ts     = strtotime($label);
            if ($ts === false) {
                continue;
            }
            $rowTimes[] = ['key' => date('H:i:s', $ts), 'label' => $label];
        }
        if (empty($rowTimes)) {
            return [];
        }

        // All active slots for the package in range → set of "Y-m-d|H:i:s".
        $existing = DB::table('tour_slot_times')
            ->where('tour_package_id', $tourPackageId)
            ->where('deleted_by', Constants::STATUS_ZERO)
            ->whereBetween('date', [$fromDate, $toDate])
            ->whereNotNull('time_from')
            ->get(['date', 'time_from']);

        if ($existing->isEmpty()) {
            return [];
        }

        $existingSet = [];
        foreach ($existing as $slot) {
            $date = date('Y-m-d', strtotime((string) $slot->date));
            $time = date('H:i:s', strtotime((string) $slot->time_from));
            $existingSet[$date . '|' . $time] = true;
        }

        // Expand the create rows over each selected date (filtered by day-of-week) and collect collisions.
        $duplicates = [];
        for ($ts = strtotime($fromDate); $ts !== false && $ts <= strtotime($toDate); $ts = strtotime('+1 day', $ts)) {
            $dow = (int) date('w', $ts); // 0 (Sun) .. 6 (Sat)
            if (!empty($days) && !in_array($dow, $days, true)) {
                continue;
            }
            $date = date('Y-m-d', $ts);
            foreach ($rowTimes as $rt) {
                if (isset($existingSet[$date . '|' . $rt['key']])) {
                    $duplicates[] = ['date' => $date, 'time' => $rt['label']];
                }
            }
        }

        return $duplicates;
    }

    /**
     * Build a human-readable alert message listing the duplicate (date + time) slots.
     */
    private function buildDuplicateSlotsMessage(array $duplicates): string
    {
        $lines = array_map(
            fn ($d) => date('M j, Y', strtotime($d['date'])) . ' · ' . $d['time'],
            $duplicates
        );
        $count = count($lines);

        // Keep the alert readable for very large overlaps — show the first 15 and summarise the rest.
        $cap = 15;
        if ($count > $cap) {
            $shown = array_slice($lines, 0, $cap);
            $lines = array_merge($shown, ['…and ' . ($count - $cap) . ' more']);
        }

        return 'These ' . $count . ' slot' . ($count > 1 ? 's' : '') . ' already exist and cannot be created: '
            . implode(', ', $lines);
    }

    /**
     * For a "convert" bulk operation, find conversions whose TARGET (new) time already has an active
     * slot on a date where the source slot exists — i.e. converting would update an existing slot.
     * A target that is itself one of the times being converted away (a shift) is allowed.
     *
     * @return array<int, array{date: string, time: string}>
     */
    private function findConvertTargetConflicts(object $request): array
    {
        $payload     = (array) ($request->payload ?? []);
        $conversions = $payload['conversions'] ?? []; // { "7:00 AM": "8:00 AM" }
        if (empty($conversions)) {
            return [];
        }

        $oldKeys = []; // 24h keys of source times (targets matching these are shifts → allowed)
        $maps    = []; // [ ['oldKey' => H:i:s, 'newKey' => H:i:s, 'newLabel' => '8:00 AM'] ]
        foreach ($conversions as $old => $new) {
            $oldTs = strtotime((string) $old);
            $newTs = strtotime((string) $new);
            if ($oldTs === false || $newTs === false) {
                continue;
            }
            $oldKey = date('H:i:s', $oldTs);
            $oldKeys[$oldKey] = true;
            $maps[] = ['oldKey' => $oldKey, 'newKey' => date('H:i:s', $newTs), 'newLabel' => trim((string) $new)];
        }
        if (empty($maps)) {
            return [];
        }

        $fromDate = $request->from_date;
        $toDate   = $request->to_date;

        // All active slots for the package in range → set of "Y-m-d|H:i:s".
        $existing = DB::table('tour_slot_times')
            ->where('tour_package_id', (int) $request->tour_package_id)
            ->where('deleted_by', Constants::STATUS_ZERO)
            ->whereBetween('date', [$fromDate, $toDate])
            ->whereNotNull('time_from')
            ->get(['date', 'time_from']);

        if ($existing->isEmpty()) {
            return [];
        }

        $existingSet = [];
        foreach ($existing as $slot) {
            $existingSet[date('Y-m-d', strtotime((string) $slot->date)) . '|' . date('H:i:s', strtotime((string) $slot->time_from))] = true;
        }

        $conflicts = [];
        for ($ts = strtotime($fromDate); $ts !== false && $ts <= strtotime($toDate); $ts = strtotime('+1 day', $ts)) {
            $date = date('Y-m-d', $ts);
            foreach ($maps as $m) {
                if (isset($oldKeys[$m['newKey']])) {
                    continue; // target is itself being converted away → shift, allowed
                }
                // The conversion only happens on dates where the source slot exists…
                if (!isset($existingSet[$date . '|' . $m['oldKey']])) {
                    continue;
                }
                // …and it conflicts when a slot already exists at the target time on that date.
                if (isset($existingSet[$date . '|' . $m['newKey']])) {
                    $conflicts[] = ['date' => $date, 'time' => $m['newLabel']];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Human-readable alert listing convert target conflicts (date + target time).
     */
    private function buildConvertConflictMessage(array $conflicts): string
    {
        $lines = array_map(
            fn ($c) => date('M j, Y', strtotime($c['date'])) . ' · ' . $c['time'],
            $conflicts
        );
        $count = count($lines);

        $cap = 15;
        if ($count > $cap) {
            $lines = array_merge(array_slice($lines, 0, $cap), ['…and ' . ($count - $cap) . ' more']);
        }

        return 'Cannot convert into ' . $count . ' time' . ($count > 1 ? 's' : '') . ' that already have a slot '
            . '(it would update the existing slot): ' . implode(', ', $lines);
    }

    /**
     * @param  object  $request
     * @return array
     *
     * @throws Exception
     */
    public function getTourSlotTimesByDate(object $request): array
    {
        try {
            $request->affiliateId = session('affiliateId') ?: $request->affiliateId;
            $request->affiliateId = $request->affiliateId ?: null;
            $tourOperator = tourOperator();
            $tourOperatorId = $tourOperator->id;
            $userType = userType();
            if ($tourOperatorId == null) {
                if (Auth::check() && Auth::user()->tour_operator_id) {
                    $tourOperatorId = Auth::user()->tour_operator_id;
                } else {
                    if (!empty($request->tourPackageId)) {
                        $tourPackage = $this->tourPackage::find($request->tourPackageId);
                        $tourOperatorId = $tourPackage->tour_operator_id ?? null;
                        session()->put('tourPackageSession', $request->tourPackageId);
                    } else {
                        $tourPackageSession = session()->get('tourPackageSession');
                        $request->tourPackageId = $tourPackageSession;
                        $tourPackage = $this->tourPackage::find($request->tourPackageId);
                        $tourOperatorId = $tourPackage->tour_operator_id ?? null;
                    }
                }
            }

            if ($tourOperatorId === null) {
                throw new Exception("To secure a slot, please choose a tour package.");
            }

            TimeSlotService::updateSlotStatus($request->date);

            $affiliateSql = '';
            $showAllPackageBookings = 1;
            if ($userType == 'Affiliate') {
                $showAllPackageBookings = CalendarService::canViewAllPackageBookings($request->tourPackageId, Auth::id());

                if ($showAllPackageBookings == Constants::STATUS_ZERO) {
                    $affiliateSql = 'AND tbm.customer_id = ' . Auth::id();
                }
            }

            //$slotTimes = TimeSlotService::getTourSlotTimes($request, $tourOperatorId, $userType, $showAllPackageBookings, $affiliateSql);
            $slotTimes = TimeSlotService::getTourSlotTimesV2($request, $tourOperatorId, $userType, $showAllPackageBookings, $affiliateSql);
            $applyOnSlots = false;
            $response = [];
            $slots = [];
            $i = 0;
            $partnerFeesTotal = 0;

            // Only for affiliate
            if ($userType == 'Affiliate' && $showAllPackageBookings == Constants::STATUS_ZERO) {
                $affiliate = $this->customer::findOrFail(Auth::id());
                $affiliateTimeSlots = TimeSlotService::getAffiliateTimeSlots($affiliate, $request);
            }

            $year = Carbon::parse($request->date)->format('Y');

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
                        WHERE tour_package_id = $request->tourPackageId
                            AND relation_year = $year
                    )");
            }
            /* END */

            /**
             * STEP 2: Load all relation packages in one go
             */
            $relatedPackages = [];
            $groupedRelations = collect();
            $packageId = $request->tourPackageId;
            if ($packageRelationSetting) {
                $relatedPackages = Cache::remember(
                    "tour_package_families_{$packageId}_{$year}",
                    3600,
                    fn() => DB::table('tour_package_families')
                        ->where('relation_year', $year)
                        ->whereIn('parent_package_id', function ($query) use ($packageId, $year) {
                            $query->select('parent_package_id')
                                ->from('tour_package_families')
                                ->where('tour_package_id', $packageId)
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
                $packageIds = $relatedPackages->pluck('tour_package_id')->unique()->push($packageId);

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
                    ->where('tst.date', $request->date)
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
                    ->where('tst.date', $request->date)
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

            foreach ($slotTimes as $key => $responseVal) {
                /* START - mapping tour package and reducing the relational seats */
                $relationPackage = [];
                $totalTourists = 0;
                if ($packageRelationSetting && $groupedRelations->has($responseVal->group_index)) {
                    $relationPackage = array_filter($tourPackageFamily, function ($obj) use ($responseVal) {
                        return $obj->tour_package_id == $responseVal->tour_package_id && $obj->group_index == $responseVal->group_index;
                    });

                    $relations = $groupedRelations[$responseVal->group_index];
                    //$totalTourists = TouristService::countTotalTourists($tourPackageFamily, $responseVal);
                    $totalTourists = TouristService::countTotalTouristsOptimized($relations, $allSlotData, $responseVal);
                }
                /* END */

                $slots[$i]['id']              = $responseVal->id;
                $slots[$i]['time_from']       = $responseVal->time_from;
                $slots[$i]['time_to']         = $responseVal->time_to;
                if ($userType == 'Affiliate' && $showAllPackageBookings == Constants::STATUS_ZERO) {
                    $affiliateTimeSlots = (isset($affiliateTimeSlots) && !empty($affiliateTimeSlots)) ? $affiliateTimeSlots : [];
                    $desiredSlot = collect($affiliateTimeSlots)->first(function ($slot) use ($responseVal) {
                        return $slot->time_from === $responseVal->time_from;
                    });

                    // Actual seats calculation
                    $bookedSeatsPerSlot = ((int) $totalTourists > 0 && !empty($relationPackage)) ? (int) $totalTourists : (int) $responseVal->booked_seats;
                    $slotSeats = $responseVal->seats;
                    $totalSeatsPerSlot = $slotSeats;

                    // Affiliate seats calculation
                    $tourists = (int) $responseVal->afl_tourists;

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

                    // Calculate affiliate availability
                    // Global remaining capacity (total slot capacity - all bookings from all customers)
                    $globalRemaining = max(0, $totalSeatsPerSlot - $bookedSeatsPerSlot);
                    
                    // For child packages: also check parent package remaining capacity
                    if ($packageRelationSetting && !empty($relationPackage) && $responseVal->is_parent == 0) {
                        // This is a child package - need to check parent capacity
                        $totalBookingsAllPackages = 0;
                        $parentSeats = 0;
                        
                        foreach ($relations as $relation) {
                            $relationSlotKey = "{$relation->tour_package_id}|{$responseVal->date}|{$responseVal->time_from}";
                            if ($allSlotData->has($relationSlotKey)) {
                                $relationSlotData = $allSlotData->get($relationSlotKey);
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
                        $affiliateRemaining = max(0, $seats - $tourists);
                        $availableSeats = min($globalRemaining, $affiliateRemaining);
                    } else {
                        // No affiliate seat limits configured - they cannot book
                        $availableSeats = 0;
                    }

                    $overbook = ($desiredSlot && isset($desiredSlot->seat_limit) && $tourists > $desiredSlot->seat_limit) ? abs($tourists - $desiredSlot->seat_limit) : 0;

                    $slots[$i]['seats'] = $seats;
                    $slots[$i]['available_tourists'] = $availableSeats;
                    $slots[$i]['tourists'] = (string) $tourists; // Use actual affiliate bookings, not calculated value
                    $slots[$i]['booked_seats'] = (int) $tourists;
                    $slots[$i]['overbooked'] = $overbook;
                } else {
                    $seats = $responseVal->seats;

                    // Use new availability calculation for parent-child relationships
                    if ($packageRelationSetting && $groupedRelations->has($responseVal->group_index)) {
                        $relations = $groupedRelations[$responseVal->group_index];
                        $availability = TouristService::calculateAvailabilityOptimized($relations, $allSlotData, $responseVal);

                        // Check if this is a parent or child package
                        $isParentPackage = $responseVal->is_parent == 1;

                        if ($isParentPackage) {
                            // For parent packages: Use actual database seat values
                            $actualSeats = $responseVal->seats;
                            $slots[$i]['seats'] = $actualSeats;
                            $slots[$i]['available_tourists'] = max(0, $actualSeats - $availability['tourists_shown']);
                            $slots[$i]['overbooked'] = max(0, $availability['tourists_shown'] - $actualSeats);
                        } else {
                            // For child packages: Use the same logic as calculateAvailabilityOptimized
                            $childSeats = $responseVal->seats;
                            $childTourists = $availability['tourists_shown'];

                            // Calculate total tourists across all related packages (parent + child)
                            $totalTouristsAllPackages = 0;
                            $parentActualSeats = 0;
                            
                            foreach ($relations as $relation) {
                                $relationSlotKey = "{$relation->tour_package_id}|{$responseVal->date}|{$responseVal->time_from}";
                                if ($allSlotData->has($relationSlotKey)) {
                                    $relationSlotData = $allSlotData->get($relationSlotKey);
                                    if (!empty($relationSlotData) && isset($relationSlotData[0])) {
                                        $relationSlot = $relationSlotData[0];
                                        $totalTouristsAllPackages += $relationSlot->total_tourists;
                                        
                                        // Get parent's actual seat capacity
                                        if ($relation->is_parent == 1) {
                                            $parentActualSeats = $relationSlot->total_seats;
                                        }
                                    }
                                }
                            }

                            // Child availability is constrained by both child capacity and parent's remaining capacity
                            $childOwnAvailability = max(0, $childSeats - $childTourists);
                            $parentRemainingCapacity = max(0, $parentActualSeats - $totalTouristsAllPackages);
                            $childAvailability = min($childOwnAvailability, $parentRemainingCapacity);

                            $slots[$i]['seats'] = $childSeats;
                            $slots[$i]['available_tourists'] = $childAvailability;
                            $slots[$i]['overbooked'] = max(0, $childTourists - $childSeats);
                        }

                        $slots[$i]['tourists'] = (string) $availability['tourists_shown'];
                        $slots[$i]['booked_seats'] = (int) $availability['tourists_shown'];
                    } else {
                        // Fallback for non-related packages - calculate actual available seats
                        $slots[$i]['seats'] = $seats;
                        $slots[$i]['available_tourists'] = max(0, $seats - $responseVal->tourists);
                        $slots[$i]['tourists'] = (string) $responseVal->tourists;
                        $slots[$i]['booked_seats'] = (int) $responseVal->tourists;
                        $slots[$i]['overbooked'] = ($responseVal->tourists > $seats) ? abs($responseVal->tourists - $seats) : 0;
                    }
                }

                $slots[$i]['bookable_status']    = $responseVal->bookable_status;
                $slots[$i]['hide_slot']    = $responseVal->hide_slot;
                $slots[$i]['afl_tourists']       = $responseVal->afl_tourists;
                $slots[$i]['affiliateFeature']   = 1;

                /* START: Custom slot mapping */
                $rateSlotMapping = 0;
                if (hasCustomRateSettingsEnabled($request->affiliateId)) {
                    $customDate = TourPackageRateService::getCustomDate($request);

                    if ($customDate && $customDate->apply_on_slots) {
                        $rateSlotMapping = TimeSlotService::getRateSlot($request, $customDate->id, $responseVal->time_from);

                        if ($applyOnSlots === false) {
                            $applyOnSlots = true;
                        }
                    }
                }

                $slots[$i]['custom_rate'] = $rateSlotMapping ? $customDate->id : 0;
                /* END: Custom slot mapping */

                $i++;
            }

            $response['slotTimes'] = $slots;

            $response['tourOperatorId'] = $tourOperatorId;

            $response['applyOnSlots'] = $applyOnSlots;

            if ($request->getRateGroups) {
                $cartRateGroupTourists = [];
                if (cartView() == 1) {
                    $cartTourPackageDetails = CartService::cartTourPackageDetails($request->tourPackageId);
                    if (!empty($cartTourPackageDetails) && !empty($cartTourPackageDetails['rate_group'])) {
                        foreach ($cartTourPackageDetails['rate_group'] as $key => $rateGroup) {
                            if (isset($cartTourPackageDetails['tourists'][$key])) {
                                $cartRateGroupTourists[$rateGroup] = $cartTourPackageDetails['tourists'][$key];
                            }
                        }
                    }
                }

                if ($request->affiliateId == null) {
                    $departmentPackage = departmentPackage();
                    if ($userType == 'Affiliate') {
                        $customer1 = $this->customer::where('is_affiliate', 'Yes')->whereNotIn('name', ['TripAdvisor', 'Bokun'])->where('bokun', 0)->where('id', Auth::id())->get(['id', 'name', 'email']);
                    } elseif ($departmentPackage) {
                        $customer1 = $this->customer::where('is_affiliate', 'Yes')->whereNotIn('name', ['TripAdvisor', 'Bokun'])->where('bokun', 0)->where('department_id', Auth::user()->department_id)->get(['id', 'name', 'email']);
                    } else {
                        $customer1 = $this->customer::where('is_affiliate', 'Yes')->whereNotIn('name', ['TripAdvisor', 'Bokun'])->where('bokun', 0)->get(['id', 'name', 'email']);
                    }
                    $option = "<option value=0>No Affiliate</option>";
                    foreach ($customer1 as $key) {
                        $option  .= "<option value ={$key->id}>{$key->name}</option>";
                    }
                    $response['customer'] = $option;
                }

                $isBlackoutDate = isBlackoutDateEnabled($request->date);
                $cashPriceEnabled = cashPriceEnabled();
                $affiliateId = (!$isBlackoutDate) ? $request->affiliateId : 0;
                $customRateDate = isCustomDateRateEnabled($request->date, $request->tourPackageId, $affiliateId);
                $tourPackageRate = $this->tourPackageRate::where('affiliate_id', $request->affiliateId)
                    ->where('scheduling_year', date('Y', strtotime($request->date)))
                    ->when($customRateDate, fn($query) => $query->where('custom_date_id', $customRateDate), fn($query) => $query->whereNull('custom_date_id'))
                    ->first();
                $rateGroups = TourPackageRateService::getRateGroupsOfTourPackage($request, $tourPackageRate, $customRateDate, $isBlackoutDate);

                $paxWiseGroupRateIds = [];
                $paxWiseGroupRates = [];
                $paxWiseGroupAdditionalCharges = [];
                $paxWiseGroupCashRates = [];
                $paxWiseGroupCashTaxes = [];
                $rateGroupsNeedsApproval = [];

                if (!empty($rateGroups)) {
                    $mangerRequest = hasSetting('MANAGER_REQUEST');
                    foreach ($rateGroups as $rateGroup) {
                        if ($request->affiliateId) {
                            $customerData = $this->customer::where('id', $request->affiliateId)->first();
                            $rateGroup->affiliate_processing_percentage = ($isBlackoutDate) ? $rateGroup->service_commission_percentage :(float) $customerData->affiliate_processing_percentage;
                            $rateGroup->stripe_affiliate_percentage = ($isBlackoutDate) ? $rateGroup->stripe_commission_percentage : $customerData->stripe_affiliate_percentage;

                            if (getNextYearCommissionPercentage($request->date)) {
                                if($isBlackoutDate) {
                                    $serviceCommission = getNormalCommissionPercentage($rateGroup->tour_package_id);
                                    $rateGroup->affiliate_processing_percentage = $serviceCommission['service_commission_percentage'];
                                    $rateGroup->stripe_affiliate_percentage = $serviceCommission['stripe_commission_percentage'];
                                } else {
                                    $affiliateCommission = getAffiliateCommissionPercentage($customerData->id);
                                    $rateGroup->affiliate_processing_percentage = (float) $affiliateCommission['affiliate_processing_percentage'];
                                    $rateGroup->stripe_affiliate_percentage = $affiliateCommission['stripe_affiliate_percentage'];
                                }
                            }
                        } elseif (getNextYearCommissionPercentage($request->date)) {
                            $serviceCommission = getNormalCommissionPercentage($rateGroup->tour_package_id);
                            $rateGroup->service_commission_percentage = $serviceCommission['service_commission_percentage'];
                            $rateGroup->stripe_commission_percentage = $serviceCommission['stripe_commission_percentage'];
                        }

                        if ($rateGroup->is_group_rate == 1) {
                            if ($rateGroup->min_group_size > 0 && $rateGroup->max_group_size > 0 && $rateGroup->min_group_size <= $rateGroup->max_group_size) {
                                for ($i = $rateGroup->min_group_size; $i <= $rateGroup->max_group_size; $i++) {
                                    $paxWiseGroupRateIds[$i] = $rateGroup->id;
                                    $paxWiseGroupRates[$i] = (float)$rateGroup->rate;
                                    $paxWiseGroupAdditionalCharges[$i] = ($rateGroup->additional_charge > 0) ? (float)$rateGroup->additional_charge : 0.00;
                                    $paxWiseGroupCashRates[$i] = ($rateGroup->is_cash_rate == 1 && $cashPriceEnabled == 1) ? (float)$rateGroup->cash_rate : (float)$rateGroup->rate;
                                    $paxWiseGroupCashTaxes[$i] = ($rateGroup->is_cash_rate == 1 && $cashPriceEnabled == 1) ? (float)$rateGroup->cash_tax : (float)$rateGroup->additional_charge;
                                }
                            }
                        }
                        if ($mangerRequest && userType() == 'Tour Operator' && $rateGroup->approval == 1) {
                            $rateGroupsNeedsApproval[] = $rateGroup->id;
                        }

                        session()->forget('affiliateId');
                        session()->save();
                        $rateGroup->cart_tourists = (!empty($cartRateGroupTourists) && isset($cartRateGroupTourists[$rateGroup->id])) ? $cartRateGroupTourists[$rateGroup->id] : 0;
                        $partnerFeesTotal += $rateGroup->partner_fee_amount;
                    }
                }

                $response['rateGroups'] = $rateGroups;
                $response['rateGroupsNeedsApproval'] = $rateGroupsNeedsApproval;
                $response['paxWiseGroupRateIds'] = $paxWiseGroupRateIds;
                $response['paxWiseGroupRates'] = $paxWiseGroupRates;
                $response['paxWiseGroupAdditionalCharges'] = $paxWiseGroupAdditionalCharges;
                $response['paxWiseGroupCashRates'] = $paxWiseGroupCashRates;
                $response['paxWiseGroupCashTaxes'] = $paxWiseGroupCashTaxes;
            } else { // request from calendar page
                $response['recordsHistory'] = ($userType == 'Affiliate') ? [] : RecordsHistoryService::getRecordsHistory($tourOperatorId, $request);
            }

            if ($request->is('api/*') && $request->tourPackageId != '') {
                $tourPackageDetails = $this->tourPackage::select('stripe_destination_id', 'overbook_seats')
                    ->where('id', $request->tourPackageId)
                    ->first();

                $response['stripe_destination_id'] = (!empty($tourOperator)) ? $tourOperator->stripe_destination_id : '';
                $response['overbook_seats'] = (!empty($tourPackageDetails)) ? $tourPackageDetails->overbook_seats : '';
            }

            $response['is_affiliate'] = $tourOperator->is_affiliate;
            $response['tourOperatorName'] =  $tourOperator->name;

            $tourPackageDetails = $this->tourPackage::find($request->tourPackageId);
            $response['isGroupRateEnabled'] = $tourPackageDetails->is_group_rate_enabled;
            $response['packageHasSlots'] = $tourPackageDetails->package_has_slots;
            $response['isPartnerPackage'] = $tourPackageDetails->is_partner_package;
            $response['isComboPackage'] = (cartView() == 1) ? $tourPackageDetails->is_combo_package : 0;

            if ($request->is('api/*')) {
                if (!empty($response['slotTimes'])) {
                    foreach ($response['slotTimes'] as $key => $slotTime) {
                        if ($slotTime['tourists'] >= $slotTime['seats']) {
                            $response['slotTimes'][$key]['bookable_status'] = 'Closed';
                        }
                    }
                }

                $tourPackageData = $this->tourPackage::find($request->tourPackageId);

                $response['service_commission_percentage'] = $tourPackageData->service_commission_percentage;
                $response['affiliate_processing_percentage'] = 0;

                if ($request->affiliateId) {
                    $customerData = $this->customer::where('id', $request->affiliateId)->first();
                    $response['affiliate_processing_percentage'] = $customerData->affiliate_processing_percentage;

                    if (getNextYearCommissionPercentage($request->date)) {
                        $affiliateCommission = getAffiliateCommissionPercentage($customerData->id);
                        $response['affiliate_processing_percentage'] = $affiliateCommission['affiliate_processing_percentage'];
                    }
                }
            }

            // Execute code only when block time duration and days/hours added for affiliates to block access for affiliates
            if ($userType == "Affiliate") {
                $affiliate = $this->customer::findOrFail(Auth::id());
                // Execute only when duration is greater than 0 AND booking-after-duration permission is NOT granted
                // If booking-after-duration is true, affiliate can book regardless of duration
                if ($affiliate->duration > 0 && !checkPermission('booking-after-duration')) {
                    foreach ($response['slotTimes'] as $key => $value) {
                        $bookingDate = $request->date;
                        $bookingTime = $value['time_from'];
                        $bookingSlotTime = Carbon::parse($bookingDate . ' ' . $bookingTime);
                        $carbonIns = Carbon::parse($bookingDate . ' ' . $bookingTime);

                        switch ($affiliate->day_hours) {
                            case 1:
                                $carbonIns->subDays($affiliate->duration);
                                break;
                            case 2:
                                $carbonIns->subHours($affiliate->duration);
                                break;
                        }

                        $response['slotTimes'][$key]['affiliateFeature'] = 1;
                        if (($carbonIns <= $bookingSlotTime) && (Carbon::now() >= $carbonIns)) {
                            $response['slotTimes'][$key]['affiliateFeature'] = 0;
                        }
                    }
                }
            }
            $response['gratuitySetting'] = TipCenterService::getAssignedGratuitySetting($request->tourPackageId);
            $response['hotels'] = [];

            if ($request->fromCreateBooking) {
                // Get package wise hotels
                $response['hotels'] = $this->hotel::where('tour_package_id', $request->tourPackageId)->get();

                $response['calendarSeats'] = ($userType == "Affiliate") ? $this->getSlotsWithRelationsForAffiliate($request->date, $tourPackageDetails, $request->tourPackageId) : $this->getSlotsWithRelations($request->date, $request->tourPackageId);
            }

            return $response;
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Get the month dates
     * @param  string  $date
     * @return array
     */
    public function getDatesOfMonth(string $date)
    {
        $inputDate = Carbon::parse($date)->startOfDay();
        $currentDate = Carbon::now()->startOfDay();

        // Check if the input date is in the same month and year as today
        $startDate = ($inputDate->isSameMonth($currentDate)) ? $currentDate : $inputDate->copy()->startOfMonth();

        $endOfMonth = $inputDate->copy()->endOfMonth();
        $dates = collect();

        while ($startDate->lte($endOfMonth)) {
            $dates->push($startDate->toDateString());
            $startDate->addDay();
        }

        return $dates->toArray();
    }

    /**
     * Get the seats month wise for non affiliate user
     * @param  string  $requestDate
     * @param  int  $packageId
     * @return mixed
     */
    public function getCalendarSeats(string $requestDate, int $packageId)
    {
        $fields = [
            'tst.id',
            'tst.tour_package_id',
            'tst.date',
            'tst.time_from',
            'tst.time_to',
            'tst.time_slot_seats AS seats',
            DB::raw('COALESCE(SUM(tbm.tourists), 0) AS tourists'),
            DB::raw('COALESCE(tpf.is_parent, 0) AS is_parent'),
            DB::raw('COALESCE(tpf.group_index, 0) AS group_index'),
        ];

        $days = self::getDatesOfMonth($requestDate); // Array of Y-m-d strings
        $year = Carbon::parse($requestDate)->format('Y');
        $slots = [];

        $query = DB::table('tour_slot_times AS tst')
            ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tst.tour_package_id')
            ->leftJoin('tour_bookings_master AS tbm', function ($join) use ($packageId) {
                $join->on('tbm.tour_slot_id', '=', 'tst.id')
                    ->where('tbm.status', '!=', 'Cancelled')
                    ->where('tbm.tour_package_id', '=', $packageId);
            })
            ->leftJoin('tour_package_families AS tpf', function ($join) use ($year) {
                $join->on('tpf.tour_package_id', '=', 'tst.tour_package_id')
                    ->whereColumn('tpf.time_from', '=', 'tst.time_from')
                    ->where('tpf.relation_year', '=', $year);
            })
            ->whereIn(DB::raw('DATE(tst.date)'), $days)
            ->where('tst.tour_package_id', $packageId)
            ->where('tst.deleted_by', Constants::STATUS_ZERO)
            ->where('tst.hide_slot', Constants::STATUS_ZERO)
            ->where('tsd.deleted_by', Constants::STATUS_ZERO)
            ->groupBy('tst.id', 'tst.tour_package_id', 'tst.date', 'tst.time_from', 'tst.time_to', 'tst.time_slot_seats', 'tpf.is_parent', 'tpf.group_index')
            ->orderBy('tst.date')
            ->orderBy('tst.time_from')
            ->select($fields);

        $slotTimes = $query->get();

        $packageRelationSetting = hasSetting('PACKAGE_RELATION');
        $tourPackageFamily = [];

        if ($packageRelationSetting) {
            $tourPackageFamily = Cache::remember("tour_package_families_{$packageId}", 3600, fn() => DB::table('tour_package_families')
                ->where('relation_year', $year)
                ->whereIn('parent_package_id', fn($query) => $query->select('parent_package_id')
                    ->from('tour_package_families')
                    ->where('tour_package_id', $packageId)
                    ->where('relation_year', $year))
                ->get()
                ->keyBy(fn($item) => "{$item->tour_package_id}-{$item->group_index}")
                ->toArray());
        }
        foreach ($slotTimes as $row) {
            if (!isset($slots[$row->date])) {
                $slots[$row->date] = [
                    'date' => $row->date,
                    'booked' => 0,
                    'seats' => 0,
                ];
            }

            $totalTourists = Constants::STATUS_ZERO;
            $relationPackage = null;

            if ($packageRelationSetting && !empty($tourPackageFamily)) {
                $key = "{$row->tour_package_id}-{$row->group_index}";
                $relationPackage = $tourPackageFamily[$key] ?? null;

                if ($relationPackage) {
                    $totalTourists = TouristService::countTotalTourists($tourPackageFamily, $row);
                }
            }

            $slots[$row->date]['booked'] += ($totalTourists > 0 && $relationPackage) ? $totalTourists : $row->tourists;
            $slots[$row->date]['seats'] += $row->seats;
        }

        return $slots;
    }

    /**
     * Get the seats month wise for affiliate user
     * @param  string  $requestDate
     * @param  object  $tourPackage
     * @param  int  $packageId
     * @param  int  $affiliateId
     * @return mixed
     */
    public function getCalendarSeatsForAffiliate(string $requestDate, $tourPackage, int $packageId, int $affiliateId = 0)
    {
        $affiliateId = $affiliateId > 0 ? $affiliateId : Auth::id();
        $affiliate = Customer::findOrFail($affiliateId);
        $duration = (int) ($affiliate->duration ?? Constants::STATUS_ZERO) * ($affiliate->day_hours == Constants::STATUS_ONE ? 24 : 1);
        $now = Carbon::now()->addHours($duration);
        $blockDate = $now->format('Y-m-d');
        $blockTime = $now->format('H:i:s');
        $year = Carbon::parse($requestDate)->format('Y');

        $fields = [
            'tst.id',
            'tst.tour_package_id',
            'tst.date',
            'tst.time_from',
            'tst.time_to',
            'tst.time_slot_seats AS seats',
            'tst.bookable_status',
            DB::raw('COALESCE(SUM(tbm.tourists), 0) AS tourists'),
            DB::raw("COALESCE(SUM(CASE WHEN tbm.customer_id = {$affiliate->id} THEN tbm.tourists ELSE 0 END), 0) AS afl_tourists"),
            DB::raw('COALESCE(tpf.is_parent, 0) AS is_parent'),
            DB::raw('COALESCE(tpf.group_index, 0) AS group_index'),
        ];

        $days = self::getDatesOfMonth($requestDate);
        $year = Carbon::parse($requestDate)->format('Y');
        $slots = [];

        $slotTimes = DB::table('tour_slot_times AS tst')
            ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tst.tour_package_id')
            ->leftJoin('tour_bookings_master AS tbm', fn($join) => $join->on('tbm.tour_slot_id', '=', 'tst.id')
                ->where('tbm.status', '!=', 'Cancelled')
                ->where('tbm.tour_package_id', '=', $packageId))
            ->leftJoin('tour_package_families AS tpf', fn($join) => $join->on('tpf.tour_package_id', '=', 'tst.tour_package_id')
                ->where('tpf.time_from', '=', 'tst.time_from')
                ->where('tpf.relation_year', '=', $year))
            ->whereIn(DB::raw('DATE(tst.date)'), $days)
            ->where('tst.tour_package_id', $packageId)
            ->where('tst.deleted_by', Constants::STATUS_ZERO)
            ->where('tsd.deleted_by', Constants::STATUS_ZERO)
            ->where('tst.hide_slot', Constants::STATUS_ZERO)
            ->whereDate('tst.date', '>=', $blockDate)
            ->when($tourPackage->package_has_slots && $requestDate == $blockDate, fn($query) => $query->whereTime('tst.time_from', '>=', $blockTime))
            ->groupBy('tst.id', 'tst.tour_package_id', 'tst.date', 'tst.time_from', 'tst.time_to', 'tst.time_slot_seats', 'tst.bookable_status', 'tpf.is_parent', 'tpf.group_index')
            ->orderBy('tst.time_from')
            ->select($fields)
            ->get();

        $affiliateTimeSlots = Cache::remember("affiliate_slots_{$affiliate->id}_{$packageId}", 3600, fn() => DB::table('tour_slot_times_affiliate AS tsta')
            ->join('affiliate_slot_times AS ast', 'ast.tour_slot_time_id', '=', 'tsta.id')
            ->where('ast.affiliate_id', $affiliate->id)
            ->where('tsta.tour_package_id', $packageId)
            ->where('tsta.status', Constants::STATUS_ACTIVE)
            ->where('tsta.deleted_by', Constants::STATUS_ZERO)
            ->select('tsta.time_from', 'tsta.time_to', 'ast.seat_limit')
            ->get()
            ->keyBy('time_from')
            ->toArray());

        $tourPackageFamily = [];
        if (hasSetting('PACKAGE_RELATION')) {
            $tourPackageFamily = Cache::remember("tour_package_families_{$packageId}", 3600, fn() => DB::table('tour_package_families')
                ->where('relation_year', $year)
                ->whereIn('parent_package_id', fn($query) => $query->select('parent_package_id')
                    ->from('tour_package_families')
                    ->where('tour_package_id', $packageId)
                    ->where('relation_year', $year))
                ->get()
                ->keyBy(fn($item) => "{$item->tour_package_id}-{$item->group_index}")
                ->toArray());
        }

        foreach ($slotTimes as $row) {
            $slots[$row->date] ??= ['date' => $row->date, 'booked' => 0, 'seats' => 0];

            $totalTourists = Constants::STATUS_ZERO;
            $relationPackage = null;
            $parentRemainingCapacity = 0;
            
            if (!empty($tourPackageFamily)) {
                $key = "{$row->tour_package_id}-{$row->group_index}";
                $relationPackage = $tourPackageFamily[$key] ?? null;

                if ($relationPackage) {
                    // Build minimal allSlotData for this relation
                    $allSlotData = collect();
                    $key = "{$row->tour_package_id}|{$row->date}|{$row->time_from}";
                    $allSlotData->put($key, [(object)[
                        'date' => $row->date,
                        'time_from' => $row->time_from,
                        'tour_package_id' => $row->tour_package_id,
                        'total_seats' => $row->seats,
                        'total_tourists' => $row->tourists
                    ]]);

                    $relations = collect([$relationPackage]);
                    $availability = TouristService::calculateAvailabilityOptimized($relations, $allSlotData, $row);
                    $parentRemainingCapacity = $availability['available_seats'];
                } else {
                    $parentRemainingCapacity = max(0, (int) $row->seats - (int) $row->tourists);
                }
            } else {
                $parentRemainingCapacity = max(0, (int) $row->seats - (int) $row->tourists);
            }

            $desiredSlot = $affiliateTimeSlots[$row->time_from] ?? null;
            $totalSeats = (int) $row->seats;
            $affiliateTourists = (int) $row->afl_tourists;
            $affiliateSeatLimit = $desiredSlot?->seat_limit ?? 0;
            $affiliateSeats = $affiliateSeatLimit > 0 ? min($affiliateSeatLimit, $totalSeats) : 0;

            $availableSeats = 0;
            if ($affiliateTourists < $affiliateSeats) {
                $affiliateAvailable = $affiliateSeats - $affiliateTourists;
                $availableSeats = min($parentRemainingCapacity, $affiliateAvailable);
            }

            $slots[$row->date]['seats'] += $availableSeats;
        }

        return $slots;
    }

    /**
     * Query to return the slot and tourist info
     *
     * @param mixed $request
     * @param mixed $tourOperatorId
     * @param mixed $packageIds
     */
    public function getSlotTimes($request, $tourOperatorId, $packageIds)
    {
        $fields = [
            'tst.id',
            'tsd.tour_package_id',
            'tst.date',
            'tst.time_from',
            'tst.time_to',
            'tst.time_slot_seats as seats',
            'tst.bookable_status',
            DB::raw("IFNULL((SELECT SUM(tbm.tourists)
                    FROM tour_bookings_master AS tbm
                    WHERE tbm.tour_slot_id = tst.id
                        AND tbm.status != 'Cancelled'
                    AND tbm.tour_package_id IN (" . implode(',', $packageIds) . ")
                    GROUP BY tbm.tour_slot_id, tbm.tour_package_id), 0) AS tourists"),
            DB::raw("IFNULL((SELECT SUM(tbm.tourists)
                    FROM tour_bookings_master AS tbm
                    JOIN customers AS c ON c.id = tbm.customer_id
                    WHERE tbm.tour_slot_id = tst.id
                        AND c.is_affiliate = 'Yes'
                        AND tbm.status != 'Cancelled'
                        AND tbm.tour_package_id IN (" . implode(',', $packageIds) . ")
                    GROUP BY tbm.tour_slot_id, tbm.tour_package_id), 0) AS afl_tourists"),
        ];

        if ($request->getRateGroups == 1) {
            $slotTimes = DB::table('tour_slot_times AS tst')
                ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
                ->where('tsd.date', $request->date) // separator is not necessary in date
                ->whereIn('tsd.tour_package_id', $packageIds)
                ->where('tsd.status', 'Active')
                ->where('tst.deleted_by', '=', '0')
                ->where('tsd.deleted_by', '=', '0')
                // ->where('tst.bookable_status', '!=', 'Closed') // In call with anil I have commented this line to display all pass slots
                ->orderBy('tst.time_from');
        } else {
            $slotTimes = DB::table('tour_slot_times AS tst')
                ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
                ->where('tsd.date', $request->date) // separator is not necessary in date
                ->whereIn('tsd.tour_package_id', $packageIds)
                ->where('tsd.status', 'Active')
                ->where('tst.deleted_by', '=', '0')
                ->where('tsd.deleted_by', '=', '0')
                ->orderBy('tst.time_from');
        }

        $slotTimes = $slotTimes->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
            ->where('tp.tour_operator_id', $tourOperatorId);

        if ($request->has('allBookableStatus')) {
            $fields[] = 'tst.bookable_status';
        }

        $slotTimes = $slotTimes->get($fields);

        $packageRelationSetting = hasSetting('PACKAGE_RELATION');

        foreach ($slotTimes as $time) {
            if ($packageRelationSetting) {
                $year = Carbon::parse($time->date)->format('Y');
                $tourPackageFamily = TourPackageFamily::where('tour_package_id', $time->tour_package_id)
                    ->where('time_from', $time->time_from)
                    ->where('relation_year', $year)
                    ->first();

                $time->is_parent = $tourPackageFamily->is_parent ?? 0;
                $time->group_index = $tourPackageFamily->group_index ?? 0;
            } else {
                $time->is_parent = 0;
                $time->group_index = 0;
            }
        }

        return $slotTimes;
    }

    /**
     * @param  int $timeSlotId
     * @return array
     *
     * @throws Exception
     */
    public function timeSlotBookingsInfo(int $timeSlotId): array
    {
        try {
            $userType = userType();

            $tourSlot = TourSlotTime::findOrFail($timeSlotId);
            $year = Carbon::parse($tourSlot->date)->format('Y');

            $tourPackage = TourPackage::findOrFail($tourSlot->tour_package_id);

            $timezone = tourOperator()->timezone;
            $showAllPackageBookings = '';
            if ($userType === 'Affiliate') {
                $showAllPackageBookings = CalendarService::showAllPackageBookings($tourSlot->tour_package_id, Auth::id());
            }

            $bookings = TimeSlotService::getBookings($timeSlotId, $tourSlot, $userType, $showAllPackageBookings);

            //Fetch relational tour packages bookings
            $packageRelationSetting = hasSetting('PACKAGE_RELATION');

            if ($packageRelationSetting) {
                // Fetch relational package bookings based on the time slot and package ID
                $relationalPackagesBookings = CalendarService::relationalPackagesBookings($timeSlotId, $tourSlot->tour_package_id);

                // Extract slot IDs from the relational package bookings
                $relationalPackagesSlotIds = $relationalPackagesBookings['slotIds'] ?? [];

                // Merge bookings if available
                if (!empty($relationalPackagesBookings['bookings'])) {
                    $bookings = $bookings->merge($relationalPackagesBookings['bookings']);
                }
            }

            if (!empty($bookings)) {
                $bookings = TimeSlotService::processBookings($bookings, $timezone);
            }

            $bookingsCancelled = TimeSlotService::getCancelledBookings($timeSlotId, $userType, $showAllPackageBookings);
            //Fetch relational tour packages cancelled bookings
            if ($packageRelationSetting) {
                $relationalPackagesBookingsCancelled = CalendarService::relationalPackagesBookings($timeSlotId, $tourSlot->tour_package_id, 'Cancelled');
                if (isset($relationalPackagesBookingsCancelled['bookings']) && $relationalPackagesBookingsCancelled['bookings']) {
                    $bookingsCancelled = $bookingsCancelled->merge($relationalPackagesBookingsCancelled['bookings']);
                }
            }

            if (!empty($bookingsCancelled)) {
                $bookingsCancelled = TimeSlotService::processBookings($bookingsCancelled, $timezone, 'Cancelled');
            }

            $slotInfo = DB::table('tour_slot_times AS tst')
                ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
                ->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
                ->where('tst.id', $timeSlotId)
                ->first(['tp.overbook_seats']);

            $tourOperatorId = Cookie::get('tourOpId') ?? Auth::user()->tour_operator_id;
            $touristsInfo = $this->timeSlotTouristsInfo($timeSlotId);
            $touristsInfoCancelled = $this->timeSlotTouristsInfoCancelled($timeSlotId);
            if ($packageRelationSetting && $relationalPackagesSlotIds) {
                //Fetch relational tour packages bookings tourists info
                $touristsInfoRelationalPackages = $this->timeSlotTouristsInfoRelationalPackagesBookings($relationalPackagesSlotIds);
                $touristsInfo = $touristsInfo->merge($touristsInfoRelationalPackages);
                // Aggregate by rate_for to prevent duplicate entries
                $touristsInfo = $touristsInfo->groupBy('rate_for')->map(function ($group) {
                    $first = $group->first();
                    $first->tourists = (string) $group->sum('tourists');
                    return $first;
                })->values();
                //Fetch relational tour packages bookings tourists info
                $touristsInfoCancelledRelationalPackages = $this->timeSlotTouristsInfoRelationalPackagesBookings($relationalPackagesSlotIds, 'Cancelled');
                $touristsInfoCancelled = $touristsInfoCancelled->merge($touristsInfoCancelledRelationalPackages);
                // Aggregate by rate_for to prevent duplicate entries
                $touristsInfoCancelled = $touristsInfoCancelled->groupBy('rate_for')->map(function ($group) {
                    $first = $group->first();
                    $first->tourists = (string) $group->sum('tourists');
                    return $first;
                })->values();
            }
            
            if ($touristsInfo === false) {
                throw new Exception("Unable to get tourists information of the time slot.");
            }

            /* START: package relation total tourists combined calculation */
            $slotTime = TouristService::getTimeSlotInfo($timeSlotId, $tourSlot->tour_package_id);

            // Use slot data for tourists count (excludes infants for seat calculations)
            // Note: $bookings->sum('tourists') includes infants which is incorrect for overbooking
            $tourists = ($userType != 'Affiliate') ? (int) $slotTime->tourists : (int) $slotTime->afl_tourists;

            if ($packageRelationSetting) {
                $tourPackageFamily = DB::select("SELECT *
                    FROM tour_package_families
                    WHERE relation_year = $year
                    AND parent_package_id IN (
                        SELECT parent_package_id
                        FROM tour_package_families
                        WHERE tour_package_id = $slotTime->tour_package_id
                            AND relation_year = $year
                    )");

                if (count($tourPackageFamily) && isset($timeSlotId) && isset($slotTime->tour_package_id)) {
                    $relationPackage = array_filter($tourPackageFamily, function ($obj) use ($slotTime) {
                        return $obj->tour_package_id == $slotTime->tour_package_id && $obj->group_index == $slotTime->group_index;
                    });

                    $totalTourists = 0;
                    if (count($relationPackage) > 0 && $slotTime) {
                        // For non-affiliate users, we already have the correct tourist count from actual bookings
                        // For affiliate users, we still need this for their calculations
                        if ($userType == 'Affiliate') {
                            $totalTourists = TouristService::countTotalTourists($tourPackageFamily, $slotTime);
                        }
                    }

                    if ($userType != 'Affiliate') {
                        // Keep our correct calculation from actual bookings - don't override it
                    } else {
                        $desiredSlot = DB::table('tour_slot_times_affiliate as tsta')
                            ->join('affiliate_slot_times as ast', 'ast.tour_slot_time_id', '=', 'tsta.id')
                            ->where('ast.affiliate_id', Auth::id())
                            ->where('tsta.tour_package_id', $slotTime->tour_package_id)
                            ->where('tsta.time_from', $slotTime->time_from)
                            ->where('tsta.status', 'Active')
                            ->where('tsta.deleted_by', 0)
                            ->select('ast.seat_limit')
                            ->first();

                        // Actual seats calculation
                        $bookedSeatsPerSlot = ((int) $totalTourists > 0 && !empty($relationPackage)) ? (int) $totalTourists : (int) $slotTime->tourists;
                        $totalSeatsPerSlot = $slotTime->seats;

                        // Affiliate seats calculation
                        $tourists = (int) $slotTime->afl_tourists;
                        $seats = isset($desiredSlot->seat_limit) ? (($slotTime->seats > 0) ? (($slotTime->seats >= $desiredSlot->seat_limit) ? ($desiredSlot->seat_limit) : ($slotTime->seats)) : 0) : 0;

                        if ($bookedSeatsPerSlot >= $totalSeatsPerSlot || $tourists >= $seats) {
                            $availableSeats = 0;
                        } else {
                            $globalTotal = $totalSeatsPerSlot - $bookedSeatsPerSlot;
                            $affliateTotal = $seats - $tourists;
                            $vacant = min($globalTotal, $affliateTotal);
                            $availableSeats = ($vacant >= $seats) ? $seats : $vacant;
                        }

                        // For affiliates, use actual affiliate bookings, not calculated value
                        $tourists = (int) $slotTime->afl_tourists;
                    }
                }
            }
            /* END: package relation total tourists combined calculation */

            // Calculate actual overbooking (current tourists - slot seats)
            $slotSeats = $slotTime->seats ?? 0;
            $actualOverbooked = max(0, $tourists - $slotSeats);
            $availableSeats = max(0, $slotSeats - $tourists);

            return [
                'bookings' => $bookings,
                'bookingsCancelled' => $bookingsCancelled,
                'touristsInfo' => $touristsInfo,
                'tourOperatorId' => $tourOperatorId,
                'touristsInfoCancelled' => $touristsInfoCancelled,
                'overBookSeats' => $actualOverbooked, // Use actual overbooking instead of configuration limit
                'totalTourists' => $tourists,
                'availableSeats' => $availableSeats, // Add available seats for consistency
                'packageHasSlots' => $tourPackage->package_has_slots,
            ];
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @param  int $timeSlotId
     * @return object
     *
     * @throws Exception
     */
    public function timeSlotTouristsInfo(int $timeSlotId)
    {
        try {
            $userType = userType();

            $touristsInfo = DB::table('tour_package_rates AS tpr')
                ->leftJoin('tour_bookings_transact AS tbt', 'tbt.tour_package_rate_id', '=', 'tpr.id')
                ->join('tour_bookings_master AS tbm', 'tbm.id', '=', 'tbt.tour_booking_id')
                ->where('tbm.tour_slot_id', $timeSlotId)
                ->whereIn('tbm.status', ['Booked', 'Completed'])
                ->where('tbt.deleted_by', 0)
                ->groupBy('tbt.tour_package_rate_id')
                ->orderByDesc('tpr.age_from')
                ->select(['tpr.rate_for',DB::raw('SUM(CASE WHEN tbt.is_infant_rate = 1 THEN tbt.infants ELSE tbt.tourists END) AS tourists')]);

            if (Auth::user()->tour_operator_id) {
                $touristsInfo = $touristsInfo->join('tour_packages AS tp', 'tp.id', '=', 'tpr.tour_package_id')
                    ->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
            }

            if ($userType == 'Affiliate') {
                $tourSlot = TourSlotTime::findOrFail($timeSlotId);

                $showAllPackageBookings = CalendarService::showAllPackageBookings($tourSlot->tour_package_id, Auth::id());

                if ($showAllPackageBookings) {
                    $touristsInfo = $touristsInfo->where('tbm.customer_id', Auth::id());
                }
            }

            return $touristsInfo->get();
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @param  int $timeSlotId
     * @return object
     *
     * @throws Exception
     */
    public function timeSlotTouristsInfoCancelled(int $timeSlotId): object
    {
        try {
            $userType = userType();
            $touristsInfo = DB::table('tour_package_rates AS tpr')
                ->leftJoin('tour_bookings_transact AS tbt', 'tbt.tour_package_rate_id', '=', 'tpr.id')
                ->join('tour_bookings_master AS tbm', 'tbm.id', '=', 'tbt.tour_booking_id')
                ->where('tbm.tour_slot_id', $timeSlotId)
                ->where('tbm.status', 'Cancelled')
                ->where('tbt.deleted_by', 0)
                ->groupBy('tbt.tour_package_rate_id')
                ->orderByDesc('tpr.age_from')
                ->select(['tpr.rate_for', DB::raw('SUM(CASE WHEN tbt.is_infant_rate = 1 THEN tbt.infants ELSE tbt.tourists END) AS tourists')]);

            if ($userType == 'Affiliate') {
                $tourSlot = TourSlotTime::findOrFail($timeSlotId);

                $showAllPackageBookings = CalendarService::showAllPackageBookings($tourSlot->tour_package_id, Auth::id());
                if ($showAllPackageBookings) {
                    $touristsInfo = $touristsInfo->where('tbm.customer_id', Auth::id());
                }
            }

            if (Auth::user()->tour_operator_id) {
                $touristsInfo = $touristsInfo->join('tour_packages AS tp', 'tp.id', '=', 'tpr.tour_package_id')
                    ->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
            }

            return $touristsInfo->get();
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @param  mixed $timeSlotId
     * @param  string $status
     * @return mixed
     *
     * @throws Exception
     */
    public function timeSlotTouristsInfoRelationalPackagesBookings(mixed $timeSlotIds, string $status = '')
    {
        try {
            $userType = userType();

            $touristsInfoQuery = DB::table('tour_package_rates AS tpr')
                ->leftJoin('tour_bookings_transact AS tbt', 'tbt.tour_package_rate_id', '=', 'tpr.id')
                ->join('tour_bookings_master AS tbm', 'tbm.id', '=', 'tbt.tour_booking_id')
                ->whereIn('tbm.tour_slot_id', $timeSlotIds)
                ->where('tbt.deleted_by', 0);

            // Apply status condition
            $touristsInfoQuery->where('tbm.status', $status == 'Cancelled' ? 'Cancelled' : ['Booked', 'Completed']);

            // Apply user-type condition
            if ($userType === 'Affiliate') {
                $touristsInfoQuery->where('tbm.customer_id', Auth::id());
            }

            // Apply tour operator condition early to filter out unnecessary rows
            if ($operatorId = Auth::user()->tour_operator_id) {
                $touristsInfoQuery->join('tour_packages AS tp', 'tp.id', '=', 'tpr.tour_package_id')
                    ->where('tp.tour_operator_id', $operatorId);
            }

            // Optimize select & group by
            $touristsInfoQuery->select([
                'tpr.rate_for',
                DB::raw('SUM(CASE WHEN tbt.is_infant_rate = 1 THEN tbt.infants ELSE tbt.tourists END) AS tourists')
            ])
                ->groupBy('tpr.rate_for')
                ->orderByDesc('tpr.age_from');

            return $touristsInfoQuery->get();
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @param  object  $request
     * @return array
     *
     * @throws Exception
     */
    public function lowerAntelopeCanyonClosureEmailSendProcess(object $request)
    {
        try {
            $bookingId = $request->booking_id ?? null;
            $slotId = $request->slot_id ?? null;
            $date = $request->date;
            $tourOperatorId = $request->tour_operator_id ?? null;
            $tourPackageId = $request->tour_package_id ?? null;

            if ($tourOperatorId) {
                $tourBookings = DB::table('tour_bookings_master AS tbm')
                    ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
                    ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
                    ->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
                    ->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
                    ->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
                    ->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
                    ->join('states AS st', 'st.id', '=', 'ct.state_id')
                    ->where('tsd.date', $date)
                    ->when($slotId != null, function ($query) use ($slotId) {
                        $query->where('tst.id', $slotId);
                    })
                    ->when($bookingId != null, function ($query) use ($bookingId) {
                        $query->where('tbm.id', $bookingId);
                    })
                    ->when($tourOperatorId != null, function ($query) use ($tourOperatorId) {
                        $query->where('t_o.id', $tourOperatorId);
                    })
                    ->when($tourPackageId != null, function ($query) use ($tourPackageId) {
                        $query->where('tp.id', $tourPackageId);
                    })
                    ->where('tbm.status', 'Booked')
                    ->select([
                        'tbm.id as tbm_id',
                        'tst.time_from',
                        'tsd.id as tour_slot_date_id',
                        'tsd.date',
                        'tp.tour_operator_id',
                        'tp.name as tour_package_name',
                        'tsd.tour_package_id',
                        'tbm.name as customer_name',
                        'tbm.email as customer_email',
                        'tbm.phone_number as customer_phone',
                        't_o.name AS tour_operator',
                        't_o.email AS tour_op_email',
                        't_o.phone_number AS tour_op_phone',
                        't_o.website AS tour_op_website',
                        't_o.logo AS tour_op_logo',
                        't_o.tenant_id',
                        'tp.email as packageEmail',
                        'tp.from_name as packageFromName',
                        'tp.from_email as packageFromEmail',
                        'tp.api_key as packageApiKey'
                    ])
                    ->get();

                if ($tourBookings->count()) {
                    $userId = Auth::id();
                    $userType = userType();

                    dispatch(new ClosureEmails($tourBookings, $userId, $userType));

                    if ($bookingId == null) {
                        DB::table('records_history')->insert([
                            'record_id_1' => ($slotId != null) ? $slotId : $tourBookings[0]->tour_slot_date_id,
                            'record_id_2' => $tourBookings[0]->tour_package_id,
                            'operation_type' => 'e',
                            'table_name' => ($slotId != null) ? 'tour_slot_times' : 'tour_slot_dates',
                            'entity_info' => ($slotId != null) ? $tourBookings[0]->date . ' ' . $tourBookings[0]->time_from : $tourBookings[0]->date,
                            'old_values' => '',
                            'new_values' => '',
                            'operation_at' => mysqlDT(),
                            'operation_by' => Auth::id(),
                            'operation_by_user_type' => userType(),
                        ]);
                    }
                }
            }

            return [];
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Bulk update for the time slots
     *
     * @param object $request
     */
    public function bulkUpdateTimeSlotByDateRange($request)
    {
        DB::beginTransaction();
        try {
            $data = $request->all();

            $dateSplit = explode(" - ", $data['date_range']);
            $startDate = strtotime($dateSplit[0]);

            $from = date('Y-m-d', strtotime($dateSplit[0]));
            $to = date('Y-m-d', strtotime($dateSplit[1]));

            if (!$request->has('operation_type') || $request->operation_type == '') {
                throw new Exception("You must need to select operation type");
            }
            if ($request->operation_type == 'Update Seats') {
                $days = (isset($data['days']) && !empty($data['days'])) ? $data['days'] : [];

                while ($startDate <= strtotime($dateSplit[1])) {
                    if (!empty($days)) {
                        $dayOfWeek = date('N', $startDate);
                        if (in_array($dayOfWeek, $days)) {
                            $date = date("Y-m-d", $startDate);
                            $data['date'] = $date;
                            $data['operation_by'] = Auth::id();
                            $data['operation_by_user_type'] = userType();

                            dispatch(new GenerateBulkSlots($data));
                        }
                    } else {
                        $date = date("Y-m-d", $startDate);
                        $data['date'] = $date;
                        $data['operation_by'] = Auth::id();
                        $data['operation_by_user_type'] = userType();

                        dispatch(new GenerateBulkSlots($data));
                    }

                    $startDate = strtotime('+1 day', $startDate);
                }
                DB::commit();
            } else {
                TourDateSlot::where('tour_package_id', $data['tour_package'])
                    ->whereBetween('date', [$from, $to])
                    ->get()
                    ->each(fn($tourSlot) => $tourSlot->delete());

                $days = (isset($data['days']) && !empty($data['days'])) ? $data['days'] : [];

                $data['hide_slot'] = 0;
                while ($startDate <= strtotime($dateSplit[1])) {
                    if (!empty($days)) {
                        $dayOfWeek = date('N', $startDate);
                        if (in_array($dayOfWeek, $days)) {
                            $date = date("Y-m-d", $startDate);
                            $data['date'] = $date;
                            $data['created_by'] = Auth::id();
                            $data['created_by_user_type'] = userType();

                            dispatch(new GenerateBulkSlots($data));
                        }
                    } else {
                        dispatch(new GenerateBulkSlots($data));
                    }

                    $startDate = strtotime('+1 day', $startDate);
                }

                DB::commit();
            }
            return response()->json([]);
        } catch (Exception $exception) {
            Log::error($exception);
            DB::rollback();
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Get Color Date for booking calendar
     * 
     * @param object $request
     */
    public function getColorDate($request)
    {
        try {
            $userType = userType();
            $date = $this->getDateForMonth($request->month, $request->year);

            if ($userType == "Affiliate") {
                $tourPackage = TourPackage::find($request->tourPackageId);
                //$data = $this->getCalendarSeatsForAffiliate($date, $tourPackage, $request->tourPackageId);
                $data = $this->getSlotsWithRelationsForAffiliate($date, $tourPackage, $request->tourPackageId);
            } else {
                //$data = $this->getCalendarSeats($date, $request->tourPackageId);
                $data = $this->getSlotsWithRelations($date, $request->tourPackageId);
            }

            return $data;
        } catch (Exception $exception) {
            Log::error($exception);
            DB::rollback();
            throw new GeneralCoreException($exception);
        }
    }
    public function getSlotsWithRelations($requestDate, $packageId)
    {
        $year = Carbon::parse($requestDate)->year;
        $days = self::getDatesOfMonth($requestDate);
        $packageRelationSetting = hasSetting('PACKAGE_RELATION');
        $slots = [];

        /**
         * STEP 1: Load slot summary data (base query)
         */
        $fields = [
            'tst.id',
            'tst.tour_package_id',
            'tst.date',
            'tst.time_from',
            'tst.time_to',
            'tst.bookable_status',
            DB::raw('COALESCE(tst.time_slot_seats, 0) AS seats'),
            'tss.booked_tourists AS tourists',
            'tss.available_seats',
            'tss.overbooked_count',
            DB::raw('COALESCE(tpf.is_parent, 0) AS is_parent'),
            DB::raw('COALESCE(tpf.group_index, 0) AS group_index'),
        ];

        $slotTimes = DB::table('tour_slot_times AS tst')
            ->leftJoin('tour_slot_summaries AS tss', function ($join) use ($packageId) {
                $join->on('tss.tour_slot_id', '=', 'tst.id')
                    ->where('tss.tour_package_id', '=', $packageId);
            })
            ->leftJoin('tour_package_families AS tpf', function ($join) use ($year) {
                $join->on('tpf.tour_package_id', '=', 'tst.tour_package_id')
                    ->whereColumn('tpf.time_from', '=', 'tst.time_from')
                    ->where('tpf.relation_year', '=', $year);
            })
            ->whereIn(DB::raw('DATE(tst.date)'), $days)
            ->where('tst.tour_package_id', $packageId)
            ->where('tst.deleted_by', Constants::STATUS_ZERO)
            ->where('tst.hide_slot', Constants::STATUS_ZERO)
            ->groupBy(
                'tst.id',
                'tst.tour_package_id',
                'tst.date',
                'tst.time_from',
                'tst.time_to',
                'tst.time_slot_seats',
                'tss.tour_package_id',
                'tpf.is_parent',
                'tpf.group_index'
            )
            ->orderBy('tst.date')
            ->orderBy('tst.time_from')
            ->select($fields)
            ->get();

        /**
         * STEP 2: Load all relation packages in one go
         */
        $relatedPackages = [];
        $groupedRelations = collect();

        if ($packageRelationSetting) {
            $relatedPackages = Cache::remember(
                "tour_package_families_{$packageId}_{$year}",
                3600,
                fn() => DB::table('tour_package_families')
                    ->where('relation_year', $year)
                    ->whereIn('parent_package_id', function ($query) use ($packageId, $year) {
                        $query->select('parent_package_id')
                            ->from('tour_package_families')
                            ->where('tour_package_id', $packageId)
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
            $packageIds = $relatedPackages->pluck('tour_package_id')->unique()->push($packageId);

            $allSlotData = DB::table('tour_slot_times AS tst')
                ->leftJoin('tour_slot_summaries AS tss', function ($join) {
                    $join->on('tss.tour_slot_id', '=', 'tst.id')
                         ->whereColumn('tss.tour_package_id', '=', 'tst.tour_package_id');
                })
                ->leftJoin('tour_bookings_master AS tbm', function ($join) {
                    $join->on('tbm.tour_slot_id', '=', 'tst.id')
                         ->whereColumn('tbm.tour_package_id', '=', 'tst.tour_package_id')
                         ->where('tbm.status', '!=', 'Cancelled');
                })
                ->select(
                    'tst.date',
                    'tst.time_from',
                    'tst.tour_package_id',
                    DB::raw('COALESCE(tst.time_slot_seats, 0) AS total_seats'),
                    DB::raw('COALESCE(tss.booked_tourists, SUM(tbm.tourists), 0) AS total_tourists')
                )
                ->whereIn(DB::raw('DATE(tst.date)'), $days)
                ->whereIn('tst.tour_package_id', $packageIds)
                ->groupBy('tst.date', 'tst.time_from', 'tst.tour_package_id', 'tss.booked_tourists', 'tst.time_slot_seats')
                ->get()
                ->groupBy(fn($row) => "{$row->tour_package_id}|{$row->date}|{$row->time_from}");
        }

        /**
         * STEP 4: Final aggregation with slot-level availability check
         */
        foreach ($slotTimes as $row) {
            if (!isset($slots[$row->date])) {
                $slots[$row->date] = [
                    'date' => $row->date,
                    'booked' => 0,
                    'seats' => 0,
                    'has_available_slots' => false, // Track if any slot has availability
                    'slots' => [], // Track individual slot availability
                ];
            }

            $totalTourists = $row->tourists;
            $totalSeats = $row->seats;

            if ($packageRelationSetting && $groupedRelations->has($row->group_index)) {
                $relations = $groupedRelations[$row->group_index];
                $totalTourists = TouristService::countTotalTouristsOptimized($relations, $allSlotData, $row);
            }

            // Check if this individual slot has availability AND is not closed
            $slotHasAvailability = $totalSeats > 0 && $totalTourists < $totalSeats && $row->bookable_status !== 'Closed';

            // Track individual slot data
            $slots[$row->date]['slots'][] = [
                'slot_id' => $row->id,
                'time_from' => $row->time_from,
                'booked' => $totalTourists,
                'seats' => $totalSeats,
                'bookable_status' => $row->bookable_status,
                'available' => $slotHasAvailability
            ];

            // If any slot has availability, mark the date as having available slots
            if ($slotHasAvailability) {
                $slots[$row->date]['has_available_slots'] = true;
            }

            $slots[$row->date]['booked'] += $totalTourists;
            $slots[$row->date]['seats'] += $totalSeats;
        }

        // Calculate CSS classes for each date based on availability
        foreach ($slots as $date => &$dateData) {
            $dateData['css_class'] = $this->calculateDateCssClass($dateData);
            $dateData['disabled'] = !$dateData['has_available_slots'] || $dateData['seats'] === 0;
        }

        return $slots;
    }

    /**
     * Get the seats month wise for affiliate user (optimized)
     */
    public function getSlotsWithRelationsForAffiliate(string $requestDate, $tourPackage, int $packageId, int $affiliateId = 0)
    {
        $affiliateId = $affiliateId > 0 ? $affiliateId : Auth::id();
        $affiliate = Customer::findOrFail($affiliateId);
        $duration = (int) ($affiliate->duration ?? Constants::STATUS_ZERO) * ($affiliate->day_hours == Constants::STATUS_ONE ? 24 : 1);
        $now = Carbon::now()->addHours($duration);
        $blockDate = $now->format('Y-m-d');
        $blockTime = $now->format('H:i:s');
        $year = Carbon::parse($requestDate)->year;
        $days = self::getDatesOfMonth($requestDate);
        $slots = [];

        /**
         * STEP 1: Fetch slot-time data (main package)
         */
        $fields = [
            'tst.id',
            'tst.tour_package_id',
            'tst.date',
            'tst.time_from',
            'tst.time_to',
            'tst.time_slot_seats AS seats',
            'tst.bookable_status',
            DB::raw('COALESCE(SUM(tbm.tourists), 0) AS tourists'),
            DB::raw("COALESCE(SUM(CASE WHEN tbm.customer_id = {$affiliateId} THEN tbm.tourists ELSE 0 END), 0) AS afl_tourists"),
            DB::raw('COALESCE(tpf.is_parent, 0) AS is_parent'),
            DB::raw('COALESCE(tpf.group_index, 0) AS group_index'),
        ];

        $slotTimes = DB::table('tour_slot_times AS tst')
            ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tst.tour_package_id')
            ->leftJoin('tour_bookings_master AS tbm', function ($join) use ($packageId) {
                $join->on('tbm.tour_slot_id', '=', 'tst.id')
                    ->where('tbm.status', '!=', 'Cancelled')
                    ->where('tbm.tour_package_id', '=', $packageId);
            })
            ->leftJoin('tour_package_families AS tpf', function ($join) use ($year) {
                $join->on('tpf.tour_package_id', '=', 'tst.tour_package_id')
                    ->whereColumn('tpf.time_from', '=', 'tst.time_from')
                    ->where('tpf.relation_year', '=', $year);
            })
            ->whereIn(DB::raw('DATE(tst.date)'), $days)
            ->where('tst.tour_package_id', $packageId)
            ->where('tst.deleted_by', Constants::STATUS_ZERO)
            ->where('tsd.deleted_by', Constants::STATUS_ZERO)
            ->where('tst.hide_slot', Constants::STATUS_ZERO)
            ->whereDate('tst.date', '>=', $blockDate)
            ->when($tourPackage->package_has_slots && $requestDate == $blockDate, fn($query) => $query->whereTime('tst.time_from', '>=', $blockTime))
            ->groupBy('tst.id', 'tst.tour_package_id', 'tst.date', 'tst.time_from', 'tst.time_to', 'tst.time_slot_seats', 'tpf.is_parent', 'tpf.group_index')
            ->orderBy('tst.time_from')
            ->select($fields)
            ->get();

        /**
         * STEP 2: Affiliate slot limits (cached)
         */
        $affiliateTimeSlots = Cache::remember("affiliate_slots_{$affiliate->id}_{$packageId}", 3600, function () use ($affiliate, $packageId) {
            return DB::table('tour_slot_times_affiliate AS tsta')
                ->join('affiliate_slot_times AS ast', 'ast.tour_slot_time_id', '=', 'tsta.id')
                ->where('ast.affiliate_id', $affiliate->id)
                ->where('tsta.tour_package_id', $packageId)
                ->where('tsta.status', Constants::STATUS_ACTIVE)
                ->where('tsta.deleted_by', Constants::STATUS_ZERO)
                ->select('tsta.time_from', 'tsta.time_to', 'ast.seat_limit')
                ->get()
                ->keyBy('time_from');
        });

        /**
         * STEP 3: Related packages (one-time cached)
         */
        $relatedPackages = collect();
        $groupedRelations = collect();

        if (hasSetting('PACKAGE_RELATION')) {
            $relatedPackages = Cache::remember("tour_package_families_{$packageId}_{$year}", 3600, function () use ($packageId, $year) {
                return DB::table('tour_package_families')
                    ->where('relation_year', $year)
                    ->whereIn('parent_package_id', function ($query) use ($packageId, $year) {
                        $query->select('parent_package_id')
                            ->from('tour_package_families')
                            ->where('tour_package_id', $packageId)
                            ->where('relation_year', $year);
                    })
                    ->get();
            });

            $groupedRelations = $relatedPackages->groupBy('group_index');
        }

        /**
         * STEP 4: Preload tourist+seat data for all related packages (single query)
         */
        $allSlotData = collect();
        if ($relatedPackages->isNotEmpty()) {
            $packageIds = $relatedPackages->pluck('tour_package_id')->unique()->push($packageId);

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
                ->whereIn(DB::raw('DATE(tst.date)'), $days)
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
                ->whereIn(DB::raw('DATE(tst.date)'), $days)
                ->whereIn('tst.tour_package_id', $packageIds)
                ->groupBy('tst.date', 'tst.time_from', 'tst.tour_package_id')
                ->get();

            // Merge the data
            foreach ($slotSummaryData as $slot) {
                $key = "{$slot->tour_package_id}|{$slot->date}|{$slot->time_from}";
                
                // Find matching booking data
                $booking = $bookingData->first(function ($b) use ($slot) {
                    return $b->tour_package_id == $slot->tour_package_id 
                        && $b->date == $slot->date 
                        && $b->time_from == $slot->time_from;
                });
                
                $tourists = $slot->summary_tourists > 0 ? $slot->summary_tourists : ($booking->booking_tourists ?? 0);
                
                $allSlotData->put($key, [(object)[
                    'date' => $slot->date,
                    'time_from' => $slot->time_from,
                    'tour_package_id' => $slot->tour_package_id,
                    'total_seats' => $slot->total_seats,
                    'total_tourists' => $tourists
                ]]);
            }
        }

        /**
         * STEP 5: Final aggregation
         */
        foreach ($slotTimes as $key => $row) {
            if (!isset($slots[$row->date])) {
                $slots[$row->date] = [
                    'date' => $row->date,
                    'booked' => 0,
                    'seats' => 0,
                    'has_available_slots' => false, // Track if any slot has availability
                    'slots' => [], // Track individual slot availability
                ];
            }

            $totalTourists = (int) $row->tourists;
            $totalSeats = (int) $row->seats;
            $affiliateTourists = (int) $row->afl_tourists;
            $desiredSlot = $affiliateTimeSlots[$row->time_from] ?? null;
            $affiliateSeatLimit = $desiredSlot?->seat_limit ?? 0;

            // Include relation package tourists/seats if applicable
            $bookedSeatsPerSlot = $totalTourists;
            $parentRemainingCapacity = 0;
            
            if ($groupedRelations->has($row->group_index)) {
                $relations = $groupedRelations[$row->group_index];
                $availability = TouristService::calculateAvailabilityOptimized($relations, $allSlotData, $row);
                
                // For affiliates, we need to consider parent's remaining capacity
                $bookedSeatsPerSlot = $availability['tourists_shown'];
                $parentRemainingCapacity = $availability['available_seats'];
            } else {
                $parentRemainingCapacity = max(0, $totalSeats - $totalTourists);
            }

            // Affiliate seats calculation
            $seats = $affiliateSeatLimit > 0 ? min($affiliateSeatLimit, $totalSeats) : 0;

            // Calculate available seats for affiliate
            if ($bookedSeatsPerSlot >= $totalSeats || $affiliateTourists >= $seats) {
                $availableSeats = 0;
            } else {
                $affliateTotal = $seats - $affiliateTourists;
                $availableSeats = min($parentRemainingCapacity, $affliateTotal);
            }

            // Check if this individual slot has availability AND is not closed
            $slotHasAvailability = $availableSeats > 0 && $row->bookable_status !== 'Closed';

            // Track individual slot data
            $slots[$row->date]['slots'][] = [
                'slot_id' => $row->id,
                'time_from' => $row->time_from,
                'booked' => $affiliateTourists,
                'seats' => $seats,
                'bookable_status' => $row->bookable_status,
                'available' => $slotHasAvailability,
                'available_seats' => $availableSeats
            ];

            // If any slot has availability, mark the date as having available slots
            if ($slotHasAvailability) {
                $slots[$row->date]['has_available_slots'] = true;
            }

            $slots[$row->date]['seats'] += $seats;
            $bookedSeats = ($seats - $availableSeats) > 0 ? (string) (($seats - $availableSeats)) : '0';
            $slots[$row->date]['booked'] += $bookedSeats;
        }

        // Calculate CSS classes for each date based on availability
        foreach ($slots as $date => &$dateData) {
            $dateData['css_class'] = $this->calculateDateCssClass($dateData);
            $dateData['disabled'] = !$dateData['has_available_slots'] || $dateData['seats'] === 0;
        }

        return $slots;
    }

    /**
     * Calculate CSS class for a date based on booking percentage and availability
     *
     * @param array $dateData
     * @return string
     */
    private function calculateDateCssClass(array $dateData): string
    {
        // If no seats, return disabled class
        if ($dateData['seats'] === 0) {
            return '';
        }

        // Check if all slots are closed
        $allSlotsClosed = true;
        foreach ($dateData['slots'] as $slot) {
            if ($slot['bookable_status'] !== 'Closed') {
                $allSlotsClosed = false;
                break;
            }
        }

        // If all slots are closed, return white background (no CSS class)
        if ($allSlotsClosed) {
            return '';
        }

        // If no available slots (but not all closed), return disabled class
        if (!$dateData['has_available_slots']) {
            return '';
        }

        // Calculate booking percentage
        $percent = $dateData['seats'] > 0 ? ($dateData['booked'] / $dateData['seats']) * 100 : 0;

        // Return appropriate CSS class based on booking percentage
        if ($percent === 0) {
            return 'bg-teal-600/20 text-neutral-900'; // Available (0% booked)
        } elseif ($percent <= 30) {
            return 'bg-amber-500/15 text-amber-700'; // Low Booking (≤ 30%)
        } elseif ($percent <= 70) {
            return 'bg-orange-400/25 text-orange-700'; // Medium Booking (31–70%)
        } else {
            return 'bg-orange-400/50 text-orange-700'; // High Booking (> 70%)
        }
    }

    /**
     * Month to get the date
     * 
     * @param int $monthNumber
     * @param $year
     */
    public function getDateForMonth(int $monthNumber, $year)
    {
        $currentYear = Carbon::now()->year;
        $currentMonth = Carbon::now()->month;

        if ($monthNumber === $currentMonth && $year === $currentYear) {
            // Return today's date
            return Carbon::now()->toDateString();
        } else {
            // Return first day of the given month in the specified year
            return Carbon::createFromDate($year, $monthNumber, 1)->toDateString();
        }
    }
}
