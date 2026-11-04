<?php

namespace App\Jobs;

use App\Models\TourSlotBulkOperation;
use App\Models\TourSlotSummary;
use App\Services\Booking\BookingService;
use App\Services\Booking\HistoryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies queued Tour Slots Bulk Manager operations to the live slot tables, in strict order.
 *
 * The job is SELF-CHAINING: it applies the first operation in its list, then dispatches a fresh
 * instance for the remaining operations — and it does so whether that operation succeeded or failed.
 * This guarantees strict one-by-one ordering (the next op is only queued after the current finishes)
 * while always continuing to the next op on failure.
 *
 * Bus::chain() is deliberately NOT used: a chain stops the moment one job fails, so Job 2 would not
 * run if Job 1 failed. Self-chaining gives the same ordering but never stops on a failure.
 */
class ApplyBulkOperationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * No timeout — a create op may build slots for a large date range.
     *
     * @var int
     */
    public $timeout = 0;

    /**
     * Do not auto-retry — a bulk apply must never run twice and risk partial double-application.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * Ordered tour_slot_bulk_operations ids still to apply: the job applies the first, chains the rest.
     *
     * @var array<int, int>
     */
    public $operationIds;

    public function __construct(array $operationIds)
    {
        $this->operationIds = array_values($operationIds);
    }

    public function handle(): void
    {
        if (empty($this->operationIds)) {
            return;
        }

        $operationId = (int) $this->operationIds[0];
        $rest        = array_slice($this->operationIds, 1);

        // applyOne() never throws; this extra guard makes doubly sure handle() reaches the chaining step.
        try {
            $this->applyOne($operationId);
        } catch (\Throwable $e) {
            Log::error($e);
        }

        // Strict ordering + continue-on-failure: queue the next op only after this one finished,
        // regardless of whether this op succeeded or failed.
        if (!empty($rest)) {
            self::dispatch($rest);
        }
    }

    /**
     * Apply a single operation. Never throws — a failure is recorded on the row and we move on.
     */
    private function applyOne(int $operationId): void
    {
        $operation = null;
        try {
            $operation = TourSlotBulkOperation::query()
                ->where('id', $operationId)
                ->where('deleted_by', 0)
                ->first();

            // Idempotent: act only on queued/claimed ops, so an already-applied op is never reprocessed.
            // (The dispatcher claims ops as 'processing' before dispatch; standalone dispatch may be 'queued'.)
            if (!$operation || !in_array($operation->status, ['queued', 'processing'], true)) {
                return;
            }

            $operation->update(['status' => 'processing']);

            switch ($operation->action) {
                case 'create':
                    $this->applyCreate($operation);
                    break;
                case 'status':
                    $this->applyStatus($operation);
                    break;
                case 'visibility':
                    $this->applyVisibility($operation);
                    break;
                case 'seats':
                    $this->applySeats($operation);
                    break;
                case 'convert':
                    $this->applyConvert($operation);
                    break;
                default:
                    // No handler yet → leave it queued so it can be applied once its handler is built.
                    $operation->update(['status' => 'queued']);
                    return;
            }

            $operation->update([
                'status'     => 'applied',
                'applied_at' => now(),
                'error'      => null,
            ]);
        } catch (\Throwable $e) {
            Log::error($e);
            $this->markFailed($operation, $e->getMessage());
        }
    }

    /**
     * Mark the operation failed without ever throwing (e.g. if the DB itself is unreachable).
     */
    private function markFailed(?TourSlotBulkOperation $operation, string $message): void
    {
        if (!$operation) {
            return;
        }
        try {
            $operation->update(['status' => 'failed', 'error' => $message]);
        } catch (\Throwable $e) {
            Log::error($e);
        }
    }

    /**
     * Backstop for when the worker kills the job before handle() finishes (timeout / OOM / fatal):
     * mark the current op failed and still chain the remaining ops so the queue isn't halted.
     */
    public function failed(\Throwable $e): void
    {
        Log::error($e);

        $operationId = $this->operationIds[0] ?? null;
        if ($operationId) {
            try {
                TourSlotBulkOperation::where('id', (int) $operationId)
                    ->where('status', 'processing')
                    ->update(['status' => 'failed', 'error' => $e->getMessage()]);
            } catch (\Throwable $ignored) {
                Log::error($ignored);
            }
        }

        $rest = array_slice($this->operationIds, 1);
        if (!empty($rest)) {
            try {
                self::dispatch($rest);
            } catch (\Throwable $ignored) {
                Log::error($ignored);
            }
        }
    }

    /**
     * Create slots: for every selected date in range (filtered by the chosen weekdays), build the
     * configured time-slot rows. Any time that ALREADY exists for a date is removed from the list so
     * a duplicate slot is never created; if every time already exists for a date, that date is
     * skipped. Reuses GenerateBulkSlots (run synchronously) for the actual insert.
     */
    private function applyCreate(TourSlotBulkOperation $operation): void
    {
        $payload    = $operation->payload ?? [];
        $createRows = $payload['createRows'] ?? [];
        $days       = array_map('intval', $payload['days'] ?? []); // 0 (Sun) .. 6 (Sat), matching the UI

        if (empty($createRows)) {
            return;
        }

        // One normalised definition per configured time-slot row (24h `from` key used for dedup).
        $rows = [];
        foreach ($createRows as $row) {
            $from = date('H:i:s', strtotime(($row['fromHour'] ?? '') . ':' . ($row['fromMinute'] ?? '00') . ' ' . strtoupper($row['fromPeriod'] ?? 'AM')));
            $rows[] = [
                'from'   => $from,
                'to'     => !empty($row['toHour'])
                    ? date('H:i:s', strtotime($row['toHour'] . ':' . ($row['toMinute'] ?? '00') . ' ' . strtoupper($row['toPeriod'] ?? 'AM')))
                    : '',
                'seats'  => (int) ($row['seats'] ?? 0),
                'status' => $row['bookableStatus'] ?? 'Open',
                'hide'   => !empty($row['hidden']) ? 1 : 0,
            ];
        }

        $fromDate = date('Y-m-d', strtotime((string) $operation->from_date));
        $toDate   = date('Y-m-d', strtotime((string) $operation->to_date));

        // Prefetch every existing time across the whole range in ONE query, grouped by date, so the
        // per-date duplicate check below is an in-memory lookup instead of a query for each day.
        $existingByDate = [];
        DB::table('tour_slot_times')
            ->where('tour_package_id', $operation->tour_package_id)
            ->whereBetween('date', [$fromDate, $toDate])
            ->where('deleted_by', 0)
            ->whereNotNull('time_from')
            ->get(['date', 'time_from'])
            ->each(function ($r) use (&$existingByDate) {
                $existingByDate[date('Y-m-d', strtotime((string) $r->date))][date('H:i:s', strtotime((string) $r->time_from))] = true;
            });

        for ($ts = strtotime($fromDate); $ts !== false && $ts <= strtotime($toDate); $ts = strtotime('+1 day', $ts)) {
            if (!empty($days) && !in_array((int) date('w', $ts), $days, true)) {
                continue;
            }
            $date = date('Y-m-d', $ts);

            // Times already present for this date → drop those rows so we never create a duplicate.
            $existing = $existingByDate[$date] ?? [];

            $dayRows = array_values(array_filter($rows, fn ($r) => !isset($existing[$r['from']])));
            if (empty($dayRows)) {
                continue; // every configured time already exists for this date → nothing to create
            }

            GenerateBulkSlots::dispatchSync([
                'from_bulk_manager'    => true, // reuse an existing TourDateSlot for the date if present
                'tour_package'         => $operation->tour_package_id,
                'date'                 => $date,
                'status'               => 'Active', // tour_slot_dates.status enum (Active/Inactive)
                'time_from'            => array_column($dayRows, 'from'),
                'time_to'              => array_column($dayRows, 'to'),
                'time_slot_seats'      => array_column($dayRows, 'seats'),
                'bookable_status'      => array_column($dayRows, 'status'),
                'hide_slot'            => array_column($dayRows, 'hide'),
                'created_by'           => $operation->created_by,
                'created_by_user_type' => $operation->created_by_user_type,
            ]);
        }
    }

    /**
     * From a slotTimes payload ([{id, ...}]) return the set of ids that STILL exist as active slots
     * for the package (keyed for O(1) isset() lookup), so we never update a slot deleted since queuing.
     *
     * @return array<int, int>
     */
    private function existingSlotIds(array $slotTimes, $tourPackageId): array
    {
        $ids = array_values(array_filter(array_map(fn ($r) => $r['id'] ?? null, $slotTimes)));
        if (empty($ids)) {
            return [];
        }

        return array_flip(
            DB::table('tour_slot_times')
                ->whereIn('id', $ids)
                ->where('tour_package_id', $tourPackageId)
                ->where('deleted_by', 0)
                ->pluck('id')
                ->all()
        );
    }

    /**
     * Update Status & Visibility: set each affected slot's bookable_status (and online show/hide via
     * hide_slot) to its queued target. The payload's slotTimes resolved every affected slot to a
     * concrete tour_slot_times.id + target status + hide flag, so we update exactly those rows that
     * still exist (run in a transaction → all-or-nothing).
     */
    private function applyStatus(TourSlotBulkOperation $operation): void
    {
        $slotTimes = $operation->payload['slotTimes'] ?? [];
        if (empty($slotTimes)) {
            return;
        }

        $validStatuses = ['Open', 'Only by Phone', 'Closed']; // tour_slot_times.bookable_status enum

        // tour_slot_times.updated_by_user_type only allows Admin / Tour Operator — set it only when the
        // operation's creator type matches, otherwise leave the column as-is to avoid an enum error.
        $userType = in_array($operation->created_by_user_type, ['Admin', 'Tour Operator'], true)
            ? $operation->created_by_user_type
            : null;

        // Only touch slots that still exist (active, same package) — skip any deleted since queuing.
        $existing = $this->existingSlotIds($slotTimes, $operation->tour_package_id);

        // Group slots by their exact target (status and/or hide) so identical changes collapse into a
        // single "UPDATE ... WHERE id IN (...)" per distinct target instead of one query per slot.
        $groups = []; // signature => ['fields' => [...], 'ids' => [...]]
        foreach ($slotTimes as $row) {
            $id     = (int) ($row['id'] ?? 0);
            $status = $row['status'] ?? null;
            $hasStatus = in_array($status, $validStatuses, true);
            $hasHide   = isset($row['hide']);

            // Status & Visibility can update status only, visibility only, or both — skip a row
            // only when the slot no longer exists or nothing was set for it.
            if (!isset($existing[$id]) || (!$hasStatus && !$hasHide)) {
                continue;
            }

            $fields = [];
            if ($hasStatus) {
                $fields['bookable_status'] = $status;
            }
            if ($hasHide) {
                $fields['hide_slot'] = ((int) $row['hide']) ? 1 : 0;
            }

            $signature = json_encode($fields);
            if (!isset($groups[$signature])) {
                $groups[$signature] = ['fields' => $fields, 'ids' => []];
            }
            $groups[$signature]['ids'][] = $id;
        }

        if (empty($groups)) {
            return;
        }

        DB::transaction(function () use ($groups, $userType, $operation) {
            foreach ($groups as $group) {
                $update = $group['fields'] + [
                    'updated_by' => $operation->created_by,
                    'updated_at' => now(),
                ];
                if ($userType !== null) {
                    $update['updated_by_user_type'] = $userType;
                }

                DB::table('tour_slot_times')
                    ->whereIn('id', $group['ids'])
                    ->where('tour_package_id', $operation->tour_package_id)
                    ->where('deleted_by', 0)
                    ->update($update);
            }
        });
    }

    /**
     * Update Visibility: show/hide the affected slots online. The op carries a single `visible` flag,
     * applied to every resolved slot id as hide_slot = visible ? 0 : 1 (run in a transaction).
     */
    private function applyVisibility(TourSlotBulkOperation $operation): void
    {
        $payload   = $operation->payload ?? [];
        $slotTimes = $payload['slotTimes'] ?? [];
        if (empty($slotTimes)) {
            return;
        }

        $hideSlot = ($payload['visible'] ?? true) ? 0 : 1; // visible → shown (0), otherwise hidden (1)

        // tour_slot_times.updated_by_user_type only allows Admin / Tour Operator — set it only when the
        // operation's creator type matches, otherwise leave the column as-is to avoid an enum error.
        $userType = in_array($operation->created_by_user_type, ['Admin', 'Tour Operator'], true)
            ? $operation->created_by_user_type
            : null;

        // Only touch slots that still exist (active, same package) — skip any deleted since queuing.
        $ids = array_keys($this->existingSlotIds($slotTimes, $operation->tour_package_id));
        if (empty($ids)) {
            return;
        }

        // Every affected slot gets the same visibility, so this is a single bulk update.
        $update = [
            'hide_slot'  => $hideSlot,
            'updated_by' => $operation->created_by,
            'updated_at' => now(),
        ];
        if ($userType !== null) {
            $update['updated_by_user_type'] = $userType;
        }

        DB::table('tour_slot_times')
            ->whereIn('id', $ids)
            ->where('tour_package_id', $operation->tour_package_id)
            ->where('deleted_by', 0)
            ->update($update);
    }

    /**
     * Update Seats: set each affected slot's time_slot_seats to its queued new count. When a
     * tour_slot_summaries row already exists for the slot, also sync its total_seats and recompute
     * available_seats / overbooked_count from the current booked_tourists (run in a transaction).
     */
    private function applySeats(TourSlotBulkOperation $operation): void
    {
        $slotTimes = $operation->payload['slotTimes'] ?? [];
        if (empty($slotTimes)) {
            return;
        }

        // tour_slot_times.updated_by_user_type only allows Admin / Tour Operator — set it only when the
        // operation's creator type matches, otherwise leave the column as-is to avoid an enum error.
        $userType = in_array($operation->created_by_user_type, ['Admin', 'Tour Operator'], true)
            ? $operation->created_by_user_type
            : null;

        // Collect the valid (slot id => new seat count) targets from the payload.
        $seatsById = [];
        foreach ($slotTimes as $row) {
            $id    = (int) ($row['id'] ?? 0);
            $seats = $row['seats'] ?? null;
            if ($id <= 0 || $seats === null || (int) $seats < 0) {
                continue; // nothing valid to apply for this slot row
            }
            $seatsById[$id] = (int) $seats;
        }
        if (empty($seatsById)) {
            return;
        }

        // Group slot ids by seat count so identical counts (e.g. "apply to all") collapse into one
        // "UPDATE ... WHERE id IN (...)" per distinct value instead of one query per slot.
        $idsBySeats = [];
        foreach ($seatsById as $id => $seats) {
            $idsBySeats[$seats][] = $id;
        }

        DB::transaction(function () use ($idsBySeats, $seatsById, $userType, $operation) {
            foreach ($idsBySeats as $seats => $ids) {
                $update = [
                    'time_slot_seats' => $seats,
                    'updated_by'      => $operation->created_by,
                    'updated_at'      => now(),
                ];
                if ($userType !== null) {
                    $update['updated_by_user_type'] = $userType;
                }

                DB::table('tour_slot_times')
                    ->whereIn('id', $ids)
                    ->where('tour_package_id', $operation->tour_package_id)
                    ->where('deleted_by', 0)
                    ->update($update);
            }

            // Keep the slot summaries in sync — fetch all affected rows in ONE query, then recompute
            // each from its own booked_tourists. Only existing rows are touched (none are created).
            $summaries = TourSlotSummary::whereIn('tour_slot_id', array_keys($seatsById))
                ->where('tour_package_id', $operation->tour_package_id)
                ->get();
            foreach ($summaries as $summary) {
                $seats = $seatsById[$summary->tour_slot_id] ?? null;
                if ($seats === null) {
                    continue;
                }
                $summary->total_seats      = $seats;
                $summary->available_seats  = max(0, $seats - $summary->booked_tourists);
                $summary->overbooked_count = max(0, $summary->booked_tourists - $seats);
                $summary->save();
            }
        });
    }

    /**
     * Convert Times: change each source slot's time to its queued new time (the slot keeps its id, so
     * its bookings move with it). Duration is preserved (time_to shifts by the same delta) and any
     * per-conversion seat/status/online overrides are applied. A slot already sitting at the new time
     * is never overwritten — that conversion is skipped (the add-to-queue validation already blocks it,
     * this is the apply-time safety net). The tour_slot_summaries row is kept in sync if it exists.
     */
    private function applyConvert(TourSlotBulkOperation $operation): void
    {
        $slotTimes = $operation->payload['slotTimes'] ?? [];
        if (empty($slotTimes)) {
            return;
        }

        $validStatuses = ['Open', 'Only by Phone', 'Closed']; // tour_slot_times.bookable_status enum

        // tour_slot_times.updated_by_user_type only allows Admin / Tour Operator — set it only when the
        // operation's creator type matches, otherwise leave the column as-is to avoid an enum error.
        $userType = in_array($operation->created_by_user_type, ['Admin', 'Tour Operator'], true)
            ? $operation->created_by_user_type
            : null;

        // tour_slot_times_affiliate.created_by_user_type only allows Admin / Tour Operator.
        $affiliateUserType = $userType ?? 'Tour Operator';

        // Display label ("Name: (Type)") for the reschedule history rows written per booking below.
        // Resolved once (same acting user for the whole op); never let it abort the conversion.
        $createdByWithName = '';
        try {
            $createdByWithName = (string) BookingService::createdUserInfo(
                false,
                null,
                (int) $operation->created_by,
                $operation->created_by_user_type
            );
        } catch (\Throwable $e) {
            Log::error($e);
        }

        // Reschedule emails are collected here and sent AFTER the transaction commits — never inside
        // it, so a later rollback can't leave customers notified of a conversion that didn't happen.
        $rescheduleEmailTasks = [];

        DB::transaction(function () use ($slotTimes, $validStatuses, $userType, $affiliateUserType, $operation, $createdByWithName, &$rescheduleEmailTasks) {
            foreach ($slotTimes as $row) {
                $id = $row['id'] ?? null;
                $to = $row['to'] ?? null;
                if (!$id || empty($to)) {
                    continue;
                }

                $newFromTs = strtotime((string) $to);
                if ($newFromTs === false) {
                    continue;
                }
                $newFrom = date('H:i:s', $newFromTs);

                // Source slot must still exist (active, same package); we also need its current times.
                $slot = DB::table('tour_slot_times')
                    ->where('id', $id)
                    ->where('tour_package_id', $operation->tour_package_id)
                    ->where('deleted_by', 0)
                    ->first(['id', 'date', 'time_from', 'time_to']);
                if (!$slot) {
                    continue; // slot no longer exists → skip
                }

                // Never overwrite an existing slot already sitting at the new time on that date.
                $targetExists = DB::table('tour_slot_times')
                    ->where('tour_package_id', $operation->tour_package_id)
                    ->where('date', $slot->date)
                    ->where('time_from', $newFrom)
                    ->where('deleted_by', 0)
                    ->where('id', '!=', $id)
                    ->exists();
                if ($targetExists) {
                    continue; // would update/duplicate an existing slot → skip
                }

                // Old start time (normalised) — the "from" side of each booking's reschedule record.
                $oldFrom = date('H:i:s', strtotime((string) $slot->time_from));

                $update = ['time_from' => $newFrom];

                // Preserve the slot's duration by shifting time_to by the same delta.
                if (!empty($slot->time_to)) {
                    $delta = $newFromTs - strtotime((string) $slot->time_from);
                    $update['time_to'] = date('H:i:s', strtotime((string) $slot->time_to) + $delta);
                }

                // Optional per-conversion overrides.
                if (isset($row['seats']) && $row['seats'] !== null && (int) $row['seats'] >= 0) {
                    $update['time_slot_seats'] = (int) $row['seats'];
                }
                if (isset($row['status']) && in_array($row['status'], $validStatuses, true)) {
                    $update['bookable_status'] = $row['status'];
                }
                if (isset($row['online']) && $row['online'] !== null) {
                    $update['hide_slot'] = $row['online'] ? 0 : 1; // true = show online (0), false = hide (1)
                }

                $update['updated_by'] = $operation->created_by;
                $update['updated_at'] = now();
                if ($userType !== null) {
                    $update['updated_by_user_type'] = $userType;
                }

                DB::table('tour_slot_times')->where('id', $id)->update($update);

                // The slot kept its id, so its bookings moved to the new time: log a Reschedule on each
                // active booking's history (only when the time actually changed) and queue a reschedule
                // email per booking (sent after commit). Best-effort — a failure here must never abort
                // the conversion itself.
                if ($newFrom !== $oldFrom) {
                    try {
                        $slotDate   = date('Y-m-d', strtotime((string) $slot->date));
                        $bookingIds = $this->logConversionReschedules($operation, (int) $id, $slotDate, $oldFrom, $newFrom, $createdByWithName);
                        if (!empty($bookingIds)) {
                            $rescheduleEmailTasks[] = [
                                'slot_id'     => (int) $id,
                                'date'        => $slotDate,
                                'old_from'    => $oldFrom,
                                'new_from'    => $newFrom,
                                'booking_ids' => $bookingIds,
                            ];
                        }
                    } catch (\Throwable $e) {
                        Log::error($e);
                    }
                }

                // Ensure an affiliate timeslot exists for the new time (create it if missing) — same as
                // the normal add/update-slot flow. Inserted immediately so repeated new times across
                // dates aren't duplicated (the next iteration's lookup will find this row).
                $affiliateRows = affiliateTimeSlots(
                    $operation->tour_package_id,
                    $newFrom,
                    $update['time_to'] ?? null,
                    now(),
                    $operation->created_by,
                    $affiliateUserType,
                    []
                );
                if (!empty($affiliateRows)) {
                    DB::table('tour_slot_times_affiliate')->insert($affiliateRows);
                }

                // Keep the slot summary in sync (only if a row already exists).
                $summary = TourSlotSummary::where('tour_slot_id', $id)
                    ->where('tour_package_id', $operation->tour_package_id)
                    ->first();
                if ($summary) {
                    $summary->time_from = $newFrom;
                    if (isset($update['time_to'])) {
                        $summary->time_to = $update['time_to'];
                    }
                    if (isset($update['time_slot_seats'])) {
                        $summary->total_seats      = (int) $update['time_slot_seats'];
                        $summary->available_seats  = max(0, $summary->total_seats - $summary->booked_tourists);
                        $summary->overbooked_count = max(0, $summary->booked_tourists - $summary->total_seats);
                    }
                    $summary->save();
                }
            }
        });

        // Transaction committed: hand the (slower) per-booking email build off to a dedicated job so
        // it never holds up this apply job or the self-chained next operation.
        if (!empty($rescheduleEmailTasks)) {
            SendConvertRescheduleEmailsJob::dispatch(
                (int) $operation->id,
                (int) $operation->created_by,
                (string) $operation->created_by_user_type,
                $rescheduleEmailTasks
            );
        }
    }

    /**
     * Insert a 'Rescheduled' tour_booking_history row for every active (non-cancelled, not soft-deleted)
     * booking sitting on a slot whose time was just converted, and return those bookings' ids so the
     * caller can hand them to the reschedule-email job. The date is unchanged — only the time moves
     * from $oldFrom to $newFrom — so each row reads "Rescheduled from <date oldFrom> to <date newFrom>".
     * Reuses HistoryService so the details JSON matches a normal reschedule exactly. The acting user
     * comes from the bulk operation (there is no Auth context inside the job).
     *
     * @return array<int, int> the affected booking ids
     */
    private function logConversionReschedules(
        TourSlotBulkOperation $operation,
        int $slotId,
        string $date,
        string $oldFrom,
        string $newFrom,
        string $createdByWithName
    ): array {
        $bookingIds = DB::table('tour_bookings_master')
            ->where('tour_slot_id', $slotId)
            ->where('status', '!=', 'Cancelled')
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();

        if (empty($bookingIds)) {
            return [];
        }

        // storeRescheduledDataInHistory only reads ->date and ->time_from off each argument.
        $from = (object) ['date' => $date, 'time_from' => $oldFrom];
        $to   = (object) ['date' => $date, 'time_from' => $newFrom];

        foreach ($bookingIds as $bookingId) {
            HistoryService::storeRescheduledDataInHistory(
                (int) $bookingId,
                $from,
                $to,
                $createdByWithName,
                (int) $operation->created_by,
                $operation->created_by_user_type
            );
        }

        return array_map('intval', $bookingIds);
    }
}
