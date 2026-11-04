<?php

namespace Tests\Unit\TourSlot;

use App\Jobs\ApplyBulkOperationJob;
use App\Models\Admin;
use App\Models\TourSlotBulkOperation;
use App\Repositories\TourSlot\TourSlotRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Unit tests for the Tour Slots Bulk Manager (TourSlotRepository).
 *
 * The focus is the per-user scoping of bulk operations: a user must only ever
 * see and act on operations they created — never another user's queued items or
 * change history — plus the create/validate path for new operations.
 *
 * These tests run against the shared tenant DB, so to stay fully isolated (and to
 * avoid the by-user Clear-History / Apply-All methods touching real data) each run
 * uses a freshly-created Admin whose id owns none of the tenant's existing rows.
 * Every row created here is tracked and hard-deleted in tearDown().
 */
class TourSlotBulkManagerTest extends TestCase
{
    protected Admin $admin;
    protected TourSlotRepository $repository;

    /** A different user id used to seed "someone else's" operations. */
    protected int $otherUserId;

    protected int $packageId;

    /** @var array<int> */
    private array $operationIds = [];

    /** @var array<int> */
    private array $packageIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Fresh admin => a unique id that owns no pre-existing bulk operations,
        // keeping every assertion exact and every mutation confined to this test.
        $this->admin = Admin::factory()->create();
        $this->actingAs($this->admin, 'admin');

        $this->otherUserId = $this->admin->id + 1_000_000;
        $this->repository  = app(TourSlotRepository::class);
        $this->packageId   = $this->createPackage();
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        if (!empty($this->operationIds)) {
            DB::table('tour_slot_bulk_operations')->whereIn('id', $this->operationIds)->delete();
        }

        foreach ($this->packageIds as $id) {
            DB::table('tour_packages')->where('id', $id)->delete();
        }

        if (isset($this->admin->id)) {
            DB::table('admins')->where('id', $this->admin->id)->delete();
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
            'name'                 => 'Bulk Manager Test Package ' . uniqid(),
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

    /**
     * Insert a tour_slot_bulk_operations row. Defaults to a valid "queued seats"
     * operation owned by the current admin; override any field via $attrs.
     */
    protected function makeOperation(array $attrs = []): TourSlotBulkOperation
    {
        $operation = TourSlotBulkOperation::create(array_merge([
            'tour_package_id'      => $this->packageId,
            'action'               => 'seats',
            'from_date'            => '2026-08-01',
            'to_date'              => '2026-08-05',
            'slots'                => ['9:00 AM'],
            'payload'              => ['created_by_with_name' => 'Bulk Tester: (Admin)'],
            'total_slots'          => 5,
            'status'               => 'queued',
            'created_by'           => $this->admin->id,
            'created_by_user_type' => 'Admin',
            'deleted_by'           => 0,
        ], $attrs));

        $this->operationIds[] = $operation->id;
        return $operation;
    }

    /** Build a Request payload for storeBulkOperation()/updateBulkOperation(). */
    protected function operationRequest(array $overrides = []): Request
    {
        return Request::create('/', 'POST', array_merge([
            'tour_package_id' => $this->packageId,
            'action'          => 'seats',
            'from_date'       => '2026-08-01',
            'to_date'         => '2026-08-05',
            'slots'           => ['9:00 AM'],
            'payload'         => ['perSlotSeats' => ['9:00 AM' => 12]],
            'total_slots'     => 5,
        ], $overrides));
    }

    // ---------------------------------------------------------------
    // storeBulkOperation
    // ---------------------------------------------------------------

    /**
     * A valid operation is stored as "queued" and stamped with the creator's id/type.
     */
    public function testStoreCreatesQueuedOperationForCurrentUser(): void
    {
        $result = $this->repository->storeBulkOperation($this->operationRequest());

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('id', $result);

        $this->operationIds[] = $result['id'];
        $operation = TourSlotBulkOperation::find($result['id']);

        $this->assertNotNull($operation);
        $this->assertSame('queued', $operation->status);
        $this->assertEquals($this->admin->id, $operation->created_by);
        $this->assertSame('Admin', $operation->created_by_user_type);
        // The creator's "Name: (Role)" label is stamped into the payload for Change History.
        $this->assertArrayHasKey('created_by_with_name', (array) $operation->payload);
    }

    /**
     * A selection-based action (seats) with no slots must be rejected, not stored.
     */
    public function testStoreRejectsSeatsOperationWithoutSlots(): void
    {
        // Omit "slots" entirely so the selection validation fails.
        $result = $this->repository->storeBulkOperation(
            $this->operationRequest(['slots' => null])
        );

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    // ---------------------------------------------------------------
    // getQueuedBulkOperations  (the reported per-user bug)
    // ---------------------------------------------------------------

    /**
     * The queue must contain the current user's operations and exclude other users'.
     */
    public function testQueuedOperationsReturnOnlyCurrentUsersOperations(): void
    {
        $mine       = $this->makeOperation();
        $otherById  = $this->makeOperation(['created_by' => $this->otherUserId]);
        $otherByType = $this->makeOperation(['created_by_user_type' => 'Tour Operator']);

        $ids = array_column($this->repository->getQueuedBulkOperations(), 'id');

        $this->assertContains($mine->id, $ids, 'The current user must see their own queued operation.');
        $this->assertNotContains($otherById->id, $ids, 'A different user id must be excluded.');
        $this->assertNotContains($otherByType->id, $ids, 'A different user type must be excluded.');
    }

    /**
     * The queue must exclude applied operations and soft-flagged (deleted_by) rows.
     */
    public function testQueuedOperationsExcludeAppliedAndDeleted(): void
    {
        $queued  = $this->makeOperation();
        $applied = $this->makeOperation(['status' => 'applied', 'applied_at' => Carbon::now()]);
        $deleted = $this->makeOperation(['deleted_by' => 999]);

        $ids = array_column($this->repository->getQueuedBulkOperations(), 'id');

        $this->assertContains($queued->id, $ids);
        $this->assertNotContains($applied->id, $ids, 'Applied operations do not belong in the queue.');
        $this->assertNotContains($deleted->id, $ids, 'Rows with deleted_by set must be excluded.');
    }

    // ---------------------------------------------------------------
    // getAppliedBulkOperations  (Change History)
    // ---------------------------------------------------------------

    /**
     * Change History must contain the current user's applied operations only.
     */
    public function testAppliedOperationsReturnOnlyCurrentUsersHistory(): void
    {
        $mine  = $this->makeOperation(['status' => 'applied', 'applied_at' => Carbon::now()]);
        $other = $this->makeOperation([
            'status'      => 'applied',
            'applied_at'  => Carbon::now(),
            'created_by'  => $this->otherUserId,
        ]);

        $ids = array_column($this->repository->getAppliedBulkOperations(), 'id');

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($other->id, $ids, "Another user's history must not be visible.");
    }

    /**
     * History lists operations that have left the queue (processing/applied/failed) and exposes each
     * operation's status. Still-queued and soft-deleted operations are excluded.
     */
    public function testHistoryReturnsNonQueuedStatusesAndExcludesQueuedAndDeleted(): void
    {
        $applied    = $this->makeOperation(['status' => 'applied', 'applied_at' => Carbon::now()]);
        $processing = $this->makeOperation(['status' => 'processing']);
        $failed     = $this->makeOperation(['status' => 'failed']);
        $queued     = $this->makeOperation(['status' => 'queued']);
        $deleted    = $this->makeOperation(['status' => 'applied', 'deleted_by' => 999]);

        $rows = collect($this->repository->getAppliedBulkOperations())->keyBy('id');

        $this->assertTrue($rows->has($applied->id));
        $this->assertTrue($rows->has($processing->id));
        $this->assertTrue($rows->has($failed->id));
        $this->assertFalse($rows->has($queued->id), 'Still-queued operations must not appear in history.');
        $this->assertFalse($rows->has($deleted->id), 'Deleted operations must be excluded.');

        // Each row exposes its processing status.
        $this->assertSame('processing', $rows[$processing->id]['status']);
        $this->assertSame('failed', $rows[$failed->id]['status']);
    }

    // ---------------------------------------------------------------
    // deleteBulkOperation / updateBulkOperation  (ownership)
    // ---------------------------------------------------------------

    /**
     * Deleting another user's operation must fail and leave the row intact.
     */
    public function testDeleteReturnsNotFoundForAnotherUsersOperation(): void
    {
        $other = $this->makeOperation(['created_by' => $this->otherUserId]);

        $result = $this->repository->deleteBulkOperation($other->id);

        $this->assertFalse($result['success']);
        $this->assertNotNull(TourSlotBulkOperation::find($other->id), 'The row must not be deleted.');
    }

    /**
     * Deleting your own operation soft-deletes it.
     */
    public function testDeleteSoftDeletesOwnOperation(): void
    {
        $mine = $this->makeOperation();

        $result = $this->repository->deleteBulkOperation($mine->id);

        $this->assertTrue($result['success']);
        $this->assertNull(TourSlotBulkOperation::find($mine->id), 'The row must no longer be visible.');
        $this->assertTrue(
            TourSlotBulkOperation::withTrashed()->find($mine->id)->trashed(),
            'The row must be soft-deleted.'
        );
    }

    /**
     * Editing another user's operation must fail and not modify the row.
     */
    public function testUpdateReturnsNotFoundForAnotherUsersOperation(): void
    {
        $other = $this->makeOperation([
            'created_by'  => $this->otherUserId,
            'total_slots' => 5,
        ]);

        $result = $this->repository->updateBulkOperation(
            $other->id,
            $this->operationRequest(['total_slots' => 99])
        );

        $this->assertFalse($result['success']);
        $this->assertEquals(5, TourSlotBulkOperation::find($other->id)->total_slots, 'The row must be unchanged.');
    }

    // ---------------------------------------------------------------
    // clearAppliedBulkOperations  (Clear History)
    // ---------------------------------------------------------------

    /**
     * Clearing history must only soft-delete the current user's applied operations.
     */
    public function testClearHistoryClearsOnlyCurrentUsersAppliedOperations(): void
    {
        $mine  = $this->makeOperation(['status' => 'applied', 'applied_at' => Carbon::now()]);
        $other = $this->makeOperation([
            'status'      => 'applied',
            'applied_at'  => Carbon::now(),
            'created_by'  => $this->otherUserId,
        ]);

        $result = $this->repository->clearAppliedBulkOperations();

        $this->assertTrue($result['success']);
        $this->assertNull(TourSlotBulkOperation::find($mine->id), "The current user's history must be cleared.");
        $this->assertNotNull(TourSlotBulkOperation::find($other->id), "Another user's history must be untouched.");
    }

    // ---------------------------------------------------------------
    // applyQueuedBulkOperations  (Apply All)
    // ---------------------------------------------------------------

    /**
     * Apply All must claim/dispatch only the current user's queued operations.
     */
    public function testApplyDispatchesOnlyCurrentUsersQueuedOperations(): void
    {
        Queue::fake();

        $mine1 = $this->makeOperation();
        $mine2 = $this->makeOperation();
        $other = $this->makeOperation(['created_by' => $this->otherUserId]);

        $result = $this->repository->applyQueuedBulkOperations();

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['dispatched'], 'Only the two operations owned by the user should be applied.');

        Queue::assertPushed(ApplyBulkOperationJob::class);

        // The current user's operations are claimed (queued -> processing)...
        $this->assertSame('processing', TourSlotBulkOperation::find($mine1->id)->status);
        $this->assertSame('processing', TourSlotBulkOperation::find($mine2->id)->status);
        // ...while another user's queued operation is left alone.
        $this->assertSame('queued', TourSlotBulkOperation::find($other->id)->status);
    }
}
