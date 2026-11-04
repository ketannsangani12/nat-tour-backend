<?php

namespace Tests\Unit\TimeSlot;

use App\Models\Admin;
use App\Services\TimeSlot\TimeSlotService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests for TimeSlotService::closeSlotOnBooking() and reopenSlotOnCancellation().
 *
 * These tests cover the slot_close_on_booking package setting that automatically
 * closes a slot when any booking is created (private/single-guide tours) and
 * reopens it only when the last active booking is cancelled on a future slot.
 *
 * Cleanup runs in tearDown() (before the base class disconnects all connections)
 * so each test leaves no data behind, even under tenancy connection switching.
 */
class TimeSlotServiceSlotClosingTest extends TestCase
{
    protected Admin $admin;

    private array $packageIds       = [];
    private array $slotDateIds      = [];
    private array $slotTimeIds      = [];
    private array $customerIds      = [];
    private array $bookingIds       = [];
    private array $packageFamilyIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($this->admin, 'admin');
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($this->bookingIds as $id) {
            DB::table('tour_booking_history')->where('tour_booking_id', $id)->delete();
            DB::table('transactions')->where('tour_booking_id', $id)->delete();
            DB::table('tour_bookings_transact')->where('tour_booking_id', $id)->delete();
            DB::table('tour_bookings_master')->where('id', $id)->delete();
        }

        foreach ($this->customerIds as $id) {
            DB::table('customers')->where('id', $id)->delete();
        }

        foreach ($this->slotTimeIds as $id) {
            DB::table('tour_slot_times')->where('id', $id)->delete();
        }

        foreach ($this->slotDateIds as $id) {
            DB::table('tour_slot_dates')->where('id', $id)->delete();
        }

        foreach ($this->packageFamilyIds as $id) {
            DB::table('tour_package_families')->where('id', $id)->delete();
        }

        foreach ($this->packageIds as $id) {
            DB::table('tour_package_rates')->where('tour_package_id', $id)->delete();
            DB::table('tour_packages')->where('id', $id)->delete();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Insert a tour_package with slot_close_on_booking configurable via $overrides.
     */
    protected function createPackage(array $overrides = []): int
    {
        $id = DB::table('tour_packages')->insertGetId([
            'tour_operator_id'      => DB::table('tour_operators')->value('id') ?? 1,
            'name'                  => 'Slot-Close Test Package ' . uniqid(),
            'duration'              => 2,
            'status'                => 'Active',
            'type'                  => 'Regular',
            'slot_close_on_booking' => 0,
            'created_by'            => 1,
            'created_by_user_type'  => 'Admin',
            'created_at'            => Carbon::now(),
            'updated_at'            => Carbon::now(),
            ...$overrides,
        ]);

        $this->packageIds[] = $id;
        return $id;
    }

    /**
     * Insert a tour_slot_dates + tour_slot_times pair for the given package/date/time.
     * Returns both IDs as an associative array.
     */
    protected function createSlot(
        int    $packageId,
        string $date,
        string $timeFrom,
        string $timeTo,
        string $bookableStatus = 'Open'
    ): array {
        $now = Carbon::now();

        $slotDateId = DB::table('tour_slot_dates')->insertGetId([
            'tour_package_id'      => $packageId,
            'date'                 => $date,
            'seats'                => 10,
            'status'               => 'Active',
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);

        $slotTimeId = DB::table('tour_slot_times')->insertGetId([
            'tour_slot_id'         => $slotDateId,
            'tour_package_id'      => $packageId,
            'date'                 => $date,
            'time_from'            => $timeFrom,
            'time_to'              => $timeTo,
            'time_slot_seats'      => 10,
            'bookable_status'      => $bookableStatus,
            'deleted_by'           => 0,   // 0 = not deleted (used by soft-delete filter)
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);

        $this->slotDateIds[] = $slotDateId;
        $this->slotTimeIds[] = $slotTimeId;

        return ['slotDateId' => $slotDateId, 'slotTimeId' => $slotTimeId];
    }

    /**
     * Insert a minimal tour_bookings_master record for the given slot.
     * deleted_at is NULL by default so whereNull('deleted_at') in
     * reopenSlotOnCancellation correctly counts it as an active booking.
     */
    protected function createBooking(int $packageId, int $slotTimeId, string $status = 'Booked'): int
    {
        $now = Carbon::now();

        $customerId = DB::table('customers')->insertGetId([
            'tour_operator_id'     => DB::table('tour_operators')->value('id') ?? 1,
            'name'                 => 'Slot Close Test Customer',
            'email'                => 'slot-close-' . uniqid() . '@test.com',
            'phone_number'         => '5550001111',
            'is_affiliate'         => 'No',
            'status'               => 'Active',
            'remember_token'       => '',
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
        ]);
        $bookingId = DB::table('tour_bookings_master')->insertGetId([
            'tour_package_id'         => $packageId,
            'tour_slot_id'            => $slotTimeId,
            'customer_id'             => $customerId,
            'name'                    => 'Slot Close Test Customer',
            'email'                   => 'slot-close@test.com',
            'phone_number'            => '5550001111',
            'tourists'                => 1,
            'infants'                 => 0,
            'comments'                => '',
            'note'                    => '',
            'liability_waiver_signing' => '{}',
            'total'                   => 50.00,
            'booking_total'           => 50.00,
            'service_commission'      => 0,
            'discount'                => 0,
            'discount2_value'         => 0,
            'discount2_percentage'    => 0,
            'status'                  => $status,
            'created_by'              => 1,
            'created_by_user_type'    => 'Admin',
            'created_at'              => $now,
            'updated_at'              => $now,
        ]);

        $this->customerIds[] = $customerId;
        $this->bookingIds[]  = $bookingId;

        return $bookingId;
    }

    /**
     * Read the current bookable_status of a slot time record.
     */
    protected function getSlotStatus(int $slotTimeId): string
    {
        return DB::table('tour_slot_times')->where('id', $slotTimeId)->value('bookable_status');
    }

    // ---------------------------------------------------------------
    // closeSlotOnBooking — 5 tests
    // ---------------------------------------------------------------

    /**
     * When slot_close_on_booking = 0 the slot must remain unchanged.
     */
    public function testCloseSlotSkipsWhenSettingDisabled(): void
    {
        $packageId = $this->createPackage(['slot_close_on_booking' => 0]);
        ['slotTimeId' => $slotTimeId] = $this->createSlot(
            $packageId, Carbon::tomorrow()->toDateString(), '09:00:00', '11:00:00', 'Open'
        );

        TimeSlotService::closeSlotOnBooking($packageId, $slotTimeId);

        $this->assertEquals('Open', $this->getSlotStatus($slotTimeId));
    }

    /**
     * When slot_close_on_booking = 1 and status is Open, the slot must be Closed.
     */
    public function testCloseSlotClosesOpenSlot(): void
    {
        $packageId = $this->createPackage(['slot_close_on_booking' => 1]);
        ['slotTimeId' => $slotTimeId] = $this->createSlot(
            $packageId, Carbon::tomorrow()->toDateString(), '09:00:00', '11:00:00', 'Open'
        );

        TimeSlotService::closeSlotOnBooking($packageId, $slotTimeId);

        $this->assertEquals('Closed', $this->getSlotStatus($slotTimeId));
    }

    /**
     * When slot_close_on_booking = 1 and status is 'Only by Phone', the slot must be Closed.
     */
    public function testCloseSlotClosesOnlyByPhoneSlot(): void
    {
        $packageId = $this->createPackage(['slot_close_on_booking' => 1]);
        ['slotTimeId' => $slotTimeId] = $this->createSlot(
            $packageId, Carbon::tomorrow()->toDateString(), '09:00:00', '11:00:00', 'Only by Phone'
        );

        TimeSlotService::closeSlotOnBooking($packageId, $slotTimeId);

        $this->assertEquals('Closed', $this->getSlotStatus($slotTimeId));
    }

    /**
     * A slot already manually set to Closed must not be double-written.
     * The whereIn guard in the service prevents the unnecessary UPDATE.
     */
    public function testCloseSlotLeavesAlreadyClosedSlotUnchanged(): void
    {
        $packageId = $this->createPackage(['slot_close_on_booking' => 1]);
        ['slotTimeId' => $slotTimeId] = $this->createSlot(
            $packageId, Carbon::tomorrow()->toDateString(), '09:00:00', '11:00:00', 'Closed'
        );

        // Record updated_at before to verify no unnecessary write happened
        $updatedAtBefore = DB::table('tour_slot_times')->where('id', $slotTimeId)->value('updated_at');

        TimeSlotService::closeSlotOnBooking($packageId, $slotTimeId);

        $this->assertEquals('Closed', $this->getSlotStatus($slotTimeId));
        $this->assertEquals(
            $updatedAtBefore,
            DB::table('tour_slot_times')->where('id', $slotTimeId)->value('updated_at'),
            'updated_at should not change when slot was already Closed'
        );
    }

    /**
     * When a slot is closed, updated_by must be set and updated_by_user_type must be 'Admin'.
     */
    public function testCloseSlotSetsAuditFields(): void
    {
        $packageId = $this->createPackage(['slot_close_on_booking' => 1]);
        ['slotTimeId' => $slotTimeId] = $this->createSlot(
            $packageId, Carbon::tomorrow()->toDateString(), '09:00:00', '11:00:00', 'Open'
        );

        TimeSlotService::closeSlotOnBooking($packageId, $slotTimeId);

        $slot = DB::table('tour_slot_times')->where('id', $slotTimeId)->first(['updated_by', 'updated_by_user_type']);
        $this->assertNotNull($slot->updated_by, 'updated_by must be set after auto-close');
        $this->assertEquals('Admin', $slot->updated_by_user_type);
    }

    // ---------------------------------------------------------------
    // reopenSlotOnCancellation — 6 tests
    // ---------------------------------------------------------------

    /**
     * When slot_close_on_booking = 0 a Closed slot must not be reopened on cancellation.
     */
    public function testReopenSlotSkipsWhenSettingDisabled(): void
    {
        $packageId = $this->createPackage(['slot_close_on_booking' => 0]);
        ['slotTimeId' => $slotTimeId] = $this->createSlot(
            $packageId, Carbon::tomorrow()->toDateString(), '09:00:00', '11:00:00', 'Closed'
        );
        $bookingId = $this->createBooking($packageId, $slotTimeId, 'Cancelled');

        TimeSlotService::reopenSlotOnCancellation($packageId, $slotTimeId, $bookingId);

        $this->assertEquals('Closed', $this->getSlotStatus($slotTimeId));
    }

    /**
     * A slot on a past date must not be reopened, regardless of booking count.
     */
    public function testReopenSlotSkipsPastDateSlot(): void
    {
        $packageId = $this->createPackage(['slot_close_on_booking' => 1]);
        ['slotTimeId' => $slotTimeId] = $this->createSlot(
            $packageId, Carbon::yesterday()->toDateString(), '09:00:00', '11:00:00', 'Closed'
        );
        $bookingId = $this->createBooking($packageId, $slotTimeId, 'Cancelled');

        TimeSlotService::reopenSlotOnCancellation($packageId, $slotTimeId, $bookingId);

        $this->assertEquals('Closed', $this->getSlotStatus($slotTimeId));
    }

    /**
     * A today's slot whose time_from has already passed must not be reopened.
     * Utilises time_from to do a full datetime comparison (not just date).
     */
    public function testReopenSlotSkipsTodaySlotWithPassedTime(): void
    {
        $packageId = $this->createPackage(['slot_close_on_booking' => 1]);
        // Derive both date and time from the same point 2 hours ago so the
        // date+time_from pair is always genuinely in the past, even when the
        // test runs between midnight and 02:00 (where subHours(2) crosses
        // midnight and time_from alone would appear to be a future time today).
        $twoHoursAgo = Carbon::now()->subHours(2);
        ['slotTimeId' => $slotTimeId] = $this->createSlot(
            $packageId, $twoHoursAgo->toDateString(), $twoHoursAgo->format('H:i:s'), '23:59:00', 'Closed'
        );
        $bookingId = $this->createBooking($packageId, $slotTimeId, 'Cancelled');

        TimeSlotService::reopenSlotOnCancellation($packageId, $slotTimeId, $bookingId);

        $this->assertEquals('Closed', $this->getSlotStatus($slotTimeId));
    }

    /**
     * When the cancelled booking was the last active booking on a future slot,
     * the slot must be reopened to 'Open'.
     */
    public function testReopenSlotReopensWhenLastBookingCancelled(): void
    {
        $packageId = $this->createPackage(['slot_close_on_booking' => 1]);
        ['slotTimeId' => $slotTimeId] = $this->createSlot(
            $packageId, Carbon::tomorrow()->toDateString(), '09:00:00', '11:00:00', 'Closed'
        );
        $bookingId = $this->createBooking($packageId, $slotTimeId, 'Cancelled');

        TimeSlotService::reopenSlotOnCancellation($packageId, $slotTimeId, $bookingId);

        $this->assertEquals('Open', $this->getSlotStatus($slotTimeId));
    }

    /**
     * When another active (non-cancelled) booking still holds the slot,
     * the slot must remain Closed even after cancelling one booking.
     */
    public function testReopenSlotSkipsWhenAnotherActiveBookingExists(): void
    {
        $packageId = $this->createPackage(['slot_close_on_booking' => 1]);
        ['slotTimeId' => $slotTimeId] = $this->createSlot(
            $packageId, Carbon::tomorrow()->toDateString(), '09:00:00', '11:00:00', 'Closed'
        );

        // Active booking that still holds the slot
        $this->createBooking($packageId, $slotTimeId, 'Booked');
        // The booking being cancelled
        $cancelledBookingId = $this->createBooking($packageId, $slotTimeId, 'Cancelled');

        TimeSlotService::reopenSlotOnCancellation($packageId, $slotTimeId, $cancelledBookingId);

        $this->assertEquals('Closed', $this->getSlotStatus($slotTimeId));
    }

    /**
     * When a slot is reopened, updated_by must be set and updated_by_user_type must be 'Admin'.
     */
    public function testReopenSlotSetsAuditFields(): void
    {
        $packageId = $this->createPackage(['slot_close_on_booking' => 1]);
        ['slotTimeId' => $slotTimeId] = $this->createSlot(
            $packageId, Carbon::tomorrow()->toDateString(), '09:00:00', '11:00:00', 'Closed'
        );
        $bookingId = $this->createBooking($packageId, $slotTimeId, 'Cancelled');

        TimeSlotService::reopenSlotOnCancellation($packageId, $slotTimeId, $bookingId);

        $slot = DB::table('tour_slot_times')->where('id', $slotTimeId)->first(['updated_by', 'updated_by_user_type']);
        $this->assertNotNull($slot->updated_by, 'updated_by must be set after auto-reopen');
        $this->assertEquals('Admin', $slot->updated_by_user_type);
    }

    // ---------------------------------------------------------------
    // Family slot close/reopen — 5 tests
    // ---------------------------------------------------------------

    /**
     * Insert a tour_package_families row linking two packages at the same
     * time_from/group_index/relation_year and return the inserted ID.
     */
    protected function createFamilyEntry(
        int    $parentPackageId,
        int    $tourPackageId,
        string $timeFrom,
        int    $groupIndex,
        int    $relationYear,
        int    $isParent = 0
    ): int {
        $operatorId = DB::table('tour_operators')->value('id') ?? 1;

        $id = DB::table('tour_package_families')->insertGetId([
            'tour_operator_id' => $operatorId,
            'parent_package_id' => $parentPackageId,
            'tour_package_id'   => $tourPackageId,
            'time_from'         => $timeFrom,
            'is_parent'         => $isParent,
            'group_index'       => $groupIndex,
            'relation_year'     => $relationYear,
            'created_at'        => Carbon::now(),
            'updated_at'        => Carbon::now(),
        ]);

        $this->packageFamilyIds[] = $id;
        return $id;
    }

    /**
     * When a package is booked and it belongs to a family, sibling slots for
     * the same date and time_from must also be Closed.
     */
    public function testCloseSlotAlsoClosesFamilySiblingSlots(): void
    {
        $date     = Carbon::tomorrow()->toDateString();
        $timeFrom = '09:00:00';
        $year     = (int) Carbon::tomorrow()->format('Y');

        $packageA = $this->createPackage(['slot_close_on_booking' => 1]);
        $packageB = $this->createPackage(['slot_close_on_booking' => 1]);
        $packageC = $this->createPackage(['slot_close_on_booking' => 1]);

        ['slotTimeId' => $slotA] = $this->createSlot($packageA, $date, $timeFrom, '11:00:00', 'Open');
        ['slotTimeId' => $slotB] = $this->createSlot($packageB, $date, $timeFrom, '11:00:00', 'Open');
        ['slotTimeId' => $slotC] = $this->createSlot($packageC, $date, $timeFrom, '11:00:00', 'Open');

        // Register all three as family group 1, parent = packageA
        $this->createFamilyEntry($packageA, $packageA, $timeFrom, 1, $year, 1);
        $this->createFamilyEntry($packageA, $packageB, $timeFrom, 1, $year, 0);
        $this->createFamilyEntry($packageA, $packageC, $timeFrom, 1, $year, 0);

        TimeSlotService::closeSlotOnBooking($packageA, $slotA);

        $this->assertEquals('Closed', $this->getSlotStatus($slotA), 'Primary slot must be Closed');
        $this->assertEquals('Closed', $this->getSlotStatus($slotB), 'Sibling B slot must be Closed');
        $this->assertEquals('Closed', $this->getSlotStatus($slotC), 'Sibling C slot must be Closed');
    }

    /**
     * A package that is NOT in any family should not affect unrelated package slots.
     * This guards against the family lookup accidentally touching non-related slots.
     */
    public function testCloseSlotDoesNotAffectUnrelatedPackages(): void
    {
        $date     = Carbon::tomorrow()->toDateString();
        $timeFrom = '10:00:00';

        $packageA    = $this->createPackage(['slot_close_on_booking' => 1]);
        $unrelated   = $this->createPackage(['slot_close_on_booking' => 1]);

        ['slotTimeId' => $slotA]          = $this->createSlot($packageA,  $date, $timeFrom, '12:00:00', 'Open');
        ['slotTimeId' => $slotUnrelated]  = $this->createSlot($unrelated, $date, $timeFrom, '12:00:00', 'Open');

        // No family entry for packageA — it is a standalone package
        TimeSlotService::closeSlotOnBooking($packageA, $slotA);

        $this->assertEquals('Closed', $this->getSlotStatus($slotA),         'Primary slot must be Closed');
        $this->assertEquals('Open',   $this->getSlotStatus($slotUnrelated), 'Unrelated slot must remain Open');
    }

    /**
     * When the last booking on the primary slot is cancelled, sibling slots
     * with no active bookings must also be reopened.
     */
    public function testReopenSlotAlsoReopensFamilySiblingSlots(): void
    {
        $date     = Carbon::tomorrow()->toDateString();
        $timeFrom = '09:00:00';
        $year     = (int) Carbon::tomorrow()->format('Y');

        $packageA = $this->createPackage(['slot_close_on_booking' => 1]);
        $packageB = $this->createPackage(['slot_close_on_booking' => 1]);

        ['slotTimeId' => $slotA] = $this->createSlot($packageA, $date, $timeFrom, '11:00:00', 'Closed');
        ['slotTimeId' => $slotB] = $this->createSlot($packageB, $date, $timeFrom, '11:00:00', 'Closed');

        $this->createFamilyEntry($packageA, $packageA, $timeFrom, 1, $year, 1);
        $this->createFamilyEntry($packageA, $packageB, $timeFrom, 1, $year, 0);

        $bookingId = $this->createBooking($packageA, $slotA, 'Cancelled');

        TimeSlotService::reopenSlotOnCancellation($packageA, $slotA, $bookingId);

        $this->assertEquals('Open', $this->getSlotStatus($slotA), 'Primary slot must be reopened');
        $this->assertEquals('Open', $this->getSlotStatus($slotB), 'Sibling B slot must be reopened');
    }

    /**
     * A sibling that has its own active booking must NOT be reopened when the
     * primary booking is cancelled, even if the primary itself is clear.
     */
    public function testReopenSlotKeepsSiblingClosedWhenSiblingHasActiveBooking(): void
    {
        $date     = Carbon::tomorrow()->toDateString();
        $timeFrom = '09:00:00';
        $year     = (int) Carbon::tomorrow()->format('Y');

        $packageA = $this->createPackage(['slot_close_on_booking' => 1]);
        $packageB = $this->createPackage(['slot_close_on_booking' => 1]);

        ['slotTimeId' => $slotA] = $this->createSlot($packageA, $date, $timeFrom, '11:00:00', 'Closed');
        ['slotTimeId' => $slotB] = $this->createSlot($packageB, $date, $timeFrom, '11:00:00', 'Closed');

        $this->createFamilyEntry($packageA, $packageA, $timeFrom, 1, $year, 1);
        $this->createFamilyEntry($packageA, $packageB, $timeFrom, 1, $year, 0);

        // B has its own independent active booking
        $this->createBooking($packageB, $slotB, 'Booked');
        $cancelledBookingId = $this->createBooking($packageA, $slotA, 'Cancelled');

        TimeSlotService::reopenSlotOnCancellation($packageA, $slotA, $cancelledBookingId);

        $this->assertEquals('Open',   $this->getSlotStatus($slotA), 'Primary slot must be reopened');
        $this->assertEquals('Closed', $this->getSlotStatus($slotB), 'Sibling B must stay Closed (has active booking)');
    }

    /**
     * When the primary slot still has an active booking and cannot be reopened,
     * sibling slots must also remain Closed.
     */
    public function testReopenSlotKeepsSiblingsClosedWhenPrimaryStillHasBooking(): void
    {
        $date     = Carbon::tomorrow()->toDateString();
        $timeFrom = '09:00:00';
        $year     = (int) Carbon::tomorrow()->format('Y');

        $packageA = $this->createPackage(['slot_close_on_booking' => 1]);
        $packageB = $this->createPackage(['slot_close_on_booking' => 1]);

        ['slotTimeId' => $slotA] = $this->createSlot($packageA, $date, $timeFrom, '11:00:00', 'Closed');
        ['slotTimeId' => $slotB] = $this->createSlot($packageB, $date, $timeFrom, '11:00:00', 'Closed');

        $this->createFamilyEntry($packageA, $packageA, $timeFrom, 1, $year, 1);
        $this->createFamilyEntry($packageA, $packageB, $timeFrom, 1, $year, 0);

        // A has two bookings; we cancel one but the other keeps it closed
        $this->createBooking($packageA, $slotA, 'Booked');
        $cancelledBookingId = $this->createBooking($packageA, $slotA, 'Cancelled');

        TimeSlotService::reopenSlotOnCancellation($packageA, $slotA, $cancelledBookingId);

        $this->assertEquals('Closed', $this->getSlotStatus($slotA), 'Primary slot must remain Closed');
        $this->assertEquals('Closed', $this->getSlotStatus($slotB), 'Sibling B must remain Closed');
    }

}
