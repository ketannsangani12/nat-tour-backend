<?php

namespace Tests\Unit\TourSlot;

use App\Jobs\ApplyBulkOperationJob;
use App\Jobs\GenerateBulkSlots;
use App\Models\TourSlotBulkOperation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Behaviour tests for ApplyBulkOperationJob's per-action appliers (status, visibility, seats, create).
 *
 * These lock in the outcome of the optimized apply paths — batched/grouped UPDATEs and the single
 * range prefetch for create — proving the query-count optimizations did not change what gets written.
 *
 * Runs against the shared tenant DB, so every row is created under a throwaway package and hard-deleted
 * (package-scoped) in tearDown().
 */
class ApplyBulkOperationJobTest extends TestCase
{
    private int $packageId;

    /** @var array<int> */
    private array $packageIds = [];

    /** @var array<int> */
    private array $operationIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->packageId = $this->createPackage();
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        if (!empty($this->operationIds)) {
            DB::table('tour_slot_bulk_operations')->whereIn('id', $this->operationIds)->delete();
        }

        foreach ($this->packageIds as $pid) {
            DB::table('tour_slot_times')->where('tour_package_id', $pid)->delete();
            DB::table('tour_slot_summaries')->where('tour_package_id', $pid)->delete();
            DB::table('tour_slot_dates')->where('tour_package_id', $pid)->delete();
            DB::table('tour_packages')->where('id', $pid)->delete();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    protected function createPackage(): int
    {
        $id = DB::table('tour_packages')->insertGetId([
            'tour_operator_id'     => DB::table('tour_operators')->value('id') ?? 1,
            'name'                 => 'Apply Job Test Package ' . uniqid(),
            'duration'             => 2,
            'status'               => 'Active',
            'type'                 => 'Regular',
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => Carbon::now(),
            'updated_at'           => Carbon::now(),
        ]);

        $this->packageIds[] = $id;
        return $id;
    }

    /** Insert a tour_slot_dates + tour_slot_times pair; returns the tour_slot_times id. */
    protected function createSlot(
        string $date,
        string $timeFrom,
        string $timeTo = '',
        int $seats = 10,
        string $status = 'Open',
        int $hide = 0,
        ?int $packageId = null
    ): int {
        $now = Carbon::now();
        $pkg = $packageId ?? $this->packageId;

        $slotDateId = DB::table('tour_slot_dates')->insertGetId([
            'tour_package_id'      => $pkg,
            'date'                 => $date,
            'seats'                => $seats,
            'status'               => 'Active',
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);

        return DB::table('tour_slot_times')->insertGetId([
            'tour_slot_id'         => $slotDateId,
            'tour_package_id'      => $pkg,
            'date'                 => $date,
            'time_from'            => $timeFrom,
            'time_to'              => $timeTo,
            'time_slot_seats'      => $seats,
            'bookable_status'      => $status,
            'hide_slot'            => $hide,
            'deleted_by'           => 0,
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);
    }

    protected function createSummary(int $slotTimeId, string $date, string $timeFrom, int $total, int $booked): void
    {
        DB::table('tour_slot_summaries')->insert([
            'tour_slot_id'     => $slotTimeId,
            'tour_package_id'  => $this->packageId,
            'date'             => $date,
            'time_from'        => $timeFrom,
            'time_to'          => '',
            'total_seats'      => $total,
            'booked_tourists'  => $booked,
            'available_seats'  => max(0, $total - $booked),
            'overbooked_count' => max(0, $booked - $total),
            'created_at'       => Carbon::now(),
            'updated_at'       => Carbon::now(),
        ]);
    }

    protected function queueOperation(string $action, array $payload, ?string $from = null, ?string $to = null): TourSlotBulkOperation
    {
        $from ??= Carbon::tomorrow()->toDateString();
        $to   ??= Carbon::tomorrow()->addDay()->toDateString();

        $operation = TourSlotBulkOperation::create([
            'tour_package_id'      => $this->packageId,
            'action'               => $action,
            'from_date'            => $from,
            'to_date'              => $to,
            'slots'                => [],
            'payload'              => $payload,
            'total_slots'          => 0,
            'status'               => 'queued',
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'deleted_by'           => 0,
        ]);

        $this->operationIds[] = $operation->id;
        return $operation;
    }

    /** Apply a single queued operation synchronously. */
    protected function apply(TourSlotBulkOperation $operation): void
    {
        (new ApplyBulkOperationJob([$operation->id]))->handle();
        $operation->refresh();
    }

    protected function slot(int $id): object
    {
        return DB::table('tour_slot_times')->where('id', $id)->first();
    }

    // ---------------------------------------------------------------
    // Status & Visibility  (grouped/batched updates)
    // ---------------------------------------------------------------

    public function testStatusAppliesSameTargetToAllSlots(): void
    {
        $date = Carbon::tomorrow()->toDateString();
        $a = $this->createSlot($date, '09:00:00', '', 10, 'Open', 0);
        $b = $this->createSlot($date, '10:00:00', '', 10, 'Open', 0);
        $c = $this->createSlot($date, '11:00:00', '', 10, 'Open', 0);

        $op = $this->queueOperation('status', ['slotTimes' => [
            ['id' => $a, 'status' => 'Closed', 'hide' => 1],
            ['id' => $b, 'status' => 'Closed', 'hide' => 1],
            ['id' => $c, 'status' => 'Closed', 'hide' => 1],
        ]]);

        $this->apply($op);

        $this->assertSame('applied', $op->status);
        foreach ([$a, $b, $c] as $id) {
            $this->assertSame('Closed', $this->slot($id)->bookable_status);
            $this->assertSame(1, (int) $this->slot($id)->hide_slot);
        }
    }

    public function testStatusAppliesMixedTargetsCorrectly(): void
    {
        $date = Carbon::tomorrow()->toDateString();
        $a = $this->createSlot($date, '09:00:00', '', 10, 'Open', 0);
        $b = $this->createSlot($date, '10:00:00', '', 10, 'Open', 0);
        $c = $this->createSlot($date, '11:00:00', '', 10, 'Open', 0);

        // a,b -> Closed+hidden ; c -> "Only by Phone" + visible  (two distinct groups)
        $op = $this->queueOperation('status', ['slotTimes' => [
            ['id' => $a, 'status' => 'Closed', 'hide' => 1],
            ['id' => $b, 'status' => 'Closed', 'hide' => 1],
            ['id' => $c, 'status' => 'Only by Phone', 'hide' => 0],
        ]]);

        $this->apply($op);

        $this->assertSame('Closed', $this->slot($a)->bookable_status);
        $this->assertSame(1, (int) $this->slot($a)->hide_slot);
        $this->assertSame('Closed', $this->slot($b)->bookable_status);
        $this->assertSame('Only by Phone', $this->slot($c)->bookable_status);
        $this->assertSame(0, (int) $this->slot($c)->hide_slot);
    }

    public function testStatusSkipsSlotsFromAnotherPackage(): void
    {
        $date = Carbon::tomorrow()->toDateString();
        $mine = $this->createSlot($date, '09:00:00', '', 10, 'Open', 0);

        // A slot belonging to a different package must not be touched.
        $otherPackageId = $this->createPackage();
        $foreignId = $this->createSlot($date, '09:00:00', '', 10, 'Open', 0, $otherPackageId);

        $op = $this->queueOperation('status', ['slotTimes' => [
            ['id' => $mine, 'status' => 'Closed'],
            ['id' => $foreignId, 'status' => 'Closed'],
        ]]);

        $this->apply($op);

        $this->assertSame('Closed', $this->slot($mine)->bookable_status);
        $this->assertSame('Open', $this->slot($foreignId)->bookable_status, 'A foreign-package slot must be untouched.');
    }

    public function testVisibilityHidesAllSlots(): void
    {
        $date = Carbon::tomorrow()->toDateString();
        $a = $this->createSlot($date, '09:00:00', '', 10, 'Open', 0);
        $b = $this->createSlot($date, '10:00:00', '', 10, 'Open', 0);

        $op = $this->queueOperation('visibility', [
            'visible'   => false,
            'slotTimes' => [['id' => $a], ['id' => $b]],
        ]);

        $this->apply($op);

        $this->assertSame(1, (int) $this->slot($a)->hide_slot);
        $this->assertSame(1, (int) $this->slot($b)->hide_slot);
    }

    // ---------------------------------------------------------------
    // Seats  (grouped updates + summary sync)
    // ---------------------------------------------------------------

    public function testSeatsUpdatesSlotsAndSyncsSummaries(): void
    {
        $date = Carbon::tomorrow()->toDateString();
        $a = $this->createSlot($date, '09:00:00', '', 10, 'Open', 0);
        $b = $this->createSlot($date, '10:00:00', '', 10, 'Open', 0);
        $this->createSummary($a, $date, '09:00:00', 10, 3);
        $this->createSummary($b, $date, '10:00:00', 10, 3);

        $op = $this->queueOperation('seats', ['slotTimes' => [
            ['id' => $a, 'seats' => 25],
            ['id' => $b, 'seats' => 25],
        ]]);

        $this->apply($op);

        $this->assertSame(25, (int) $this->slot($a)->time_slot_seats);
        $this->assertSame(25, (int) $this->slot($b)->time_slot_seats);

        $summaryA = DB::table('tour_slot_summaries')->where('tour_slot_id', $a)->first();
        $this->assertSame(25, (int) $summaryA->total_seats);
        $this->assertSame(22, (int) $summaryA->available_seats); // 25 - 3
        $this->assertSame(0, (int) $summaryA->overbooked_count);
    }

    public function testSeatsRecomputesOverbookedWhenReducedBelowBooked(): void
    {
        $date = Carbon::tomorrow()->toDateString();
        $a = $this->createSlot($date, '09:00:00', '', 40, 'Open', 0);
        $this->createSummary($a, $date, '09:00:00', 40, 30); // 30 already booked

        $op = $this->queueOperation('seats', ['slotTimes' => [['id' => $a, 'seats' => 25]]]);

        $this->apply($op);

        $summary = DB::table('tour_slot_summaries')->where('tour_slot_id', $a)->first();
        $this->assertSame(25, (int) $summary->total_seats);
        $this->assertSame(0, (int) $summary->available_seats);   // max(0, 25 - 30)
        $this->assertSame(5, (int) $summary->overbooked_count);  // max(0, 30 - 25)
    }

    // ---------------------------------------------------------------
    // Create  (single range prefetch drives the per-date dedup)
    // ---------------------------------------------------------------

    public function testCreateSkipsDatesWhereTimeAlreadyExists(): void
    {
        Bus::fake();

        $d1 = Carbon::tomorrow()->toDateString();
        $d2 = Carbon::tomorrow()->addDay()->toDateString();

        // 7:00 AM already exists on d1 → that date must be skipped; d2 must be created.
        $this->createSlot($d1, '07:00:00', '08:00:00');

        $op = $this->queueOperation('create', [
            'days'       => [],
            'createRows' => [[
                'fromHour' => '7', 'fromMinute' => '00', 'fromPeriod' => 'AM',
                'toHour' => '8', 'toMinute' => '00', 'toPeriod' => 'AM',
                'seats' => 10, 'bookableStatus' => 'Open', 'hidden' => false,
            ]],
        ], $d1, $d2);

        $this->apply($op);

        $this->assertSame('applied', $op->status);
        // Only d2 triggers a creation (d1 already has 7:00 AM) — proves the prefetch dedup works.
        Bus::assertDispatchedSyncTimes(GenerateBulkSlots::class, 1);
    }

    public function testCreateDispatchesForEveryDateWhenNoneExist(): void
    {
        Bus::fake();

        $d1 = Carbon::tomorrow()->toDateString();
        $d2 = Carbon::tomorrow()->addDay()->toDateString();

        $op = $this->queueOperation('create', [
            'days'       => [],
            'createRows' => [[
                'fromHour' => '7', 'fromMinute' => '00', 'fromPeriod' => 'AM',
                'toHour' => '8', 'toMinute' => '00', 'toPeriod' => 'AM',
                'seats' => 10, 'bookableStatus' => 'Open', 'hidden' => false,
            ]],
        ], $d1, $d2);

        $this->apply($op);

        // No existing slots → both dates in the range are created.
        Bus::assertDispatchedSyncTimes(GenerateBulkSlots::class, 2);
    }
}
