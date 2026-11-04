<?php

namespace Tests\Unit\Partner;

use App\Models\TourPackage;
use App\Services\PartnerBookings;
use App\Utils\BokunUtils;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests for the cross-tenant engine (App\Services\PartnerBookings).
 *
 * Run with:
 *   docker exec -it nat-app php artisan test tests/Unit/Partner/PartnerBookingsTest.php
 *
 * Origins are defined by rows in partner_cross_tenant_origins (one row = single
 * passthrough, two or more = combo), each carrying the origin's own time_from.
 * To keep fixtures reliable in the shared dev DB, origins are created in the same
 * tenant the test runs in (tenant_dixies). runInTenant() still exercises the real
 * tenancy switch (Tenant::find + tenancy initialize/end + restore); the mechanism
 * is identical whether the origin DB is the same or different. Every row written
 * is tracked and removed in tearDown(), deepest-first.
 */
class PartnerBookingsTest extends TestCase
{
    private const TENANT = 'dixies';

    protected array $cleanupPackageIds     = [];
    protected array $cleanupDateIds        = [];
    protected array $cleanupSlotIds        = [];
    protected array $cleanupTourBookingIds = [];
    protected array $cleanupOriginMapIds   = [];
    protected array $cleanupRateIds        = [];
    protected array $cleanupCustomerIds    = [];

    protected function tearDown(): void
    {
        if (!empty($this->cleanupTourBookingIds)) {
            DB::connection('tenant')->table('tour_booking_history')->whereIn('tour_booking_id', $this->cleanupTourBookingIds)->delete();
            DB::connection('tenant')->table('transactions')->whereIn('tour_booking_id', $this->cleanupTourBookingIds)->delete();
            DB::connection('tenant')->table('tour_bookings_transact')->whereIn('tour_booking_id', $this->cleanupTourBookingIds)->delete();
            DB::connection('tenant')->table('audits')
                ->where('auditable_type', \App\Models\TourBooking::class)
                ->whereIn('auditable_id', $this->cleanupTourBookingIds)
                ->delete();
            DB::connection('tenant')->table('tour_bookings_master')->whereIn('id', $this->cleanupTourBookingIds)->delete();
        }
        if (!empty($this->cleanupOriginMapIds)) {
            DB::connection('tenant')->table('partner_cross_tenant_origins')->whereIn('id', $this->cleanupOriginMapIds)->delete();
        }
        if (!empty($this->cleanupSlotIds)) {
            DB::connection('tenant')->table('tour_slot_summaries')->whereIn('tour_slot_id', $this->cleanupSlotIds)->delete();
            DB::connection('tenant')->table('tour_slot_times')->whereIn('id', $this->cleanupSlotIds)->delete();
        }
        if (!empty($this->cleanupDateIds)) {
            DB::connection('tenant')->table('tour_slot_dates')->whereIn('id', $this->cleanupDateIds)->delete();
        }
        if (!empty($this->cleanupRateIds)) {
            DB::connection('tenant')->table('tour_package_rates')->whereIn('id', $this->cleanupRateIds)->delete();
        }
        if (!empty($this->cleanupPackageIds)) {
            DB::connection('tenant')->table('tour_packages')->whereIn('id', $this->cleanupPackageIds)->delete();
        }
        if (!empty($this->cleanupCustomerIds)) {
            DB::connection('tenant')->table('customers')->whereIn('id', $this->cleanupCustomerIds)->delete();
        }

        parent::tearDown();
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function createPackage(array $overrides = []): int
    {
        $operatorId = DB::connection('tenant')->table('tour_operators')->value('id');
        $this->assertNotNull($operatorId, 'Test requires a tour_operator in tenant_' . self::TENANT . '.');

        $id = DB::connection('tenant')->table('tour_packages')->insertGetId(array_merge([
            'tour_operator_id'     => $operatorId,
            'name'                 => 'Partner Cross-Tenant Test ' . uniqid(),
            'status'               => 'Active',
            'type'                 => 'Regular',
            'overbook_seats'       => 0,
            'slot_close_on_booking'=> 0,
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => Carbon::now(),
            'updated_at'           => Carbon::now(),
        ], $overrides));

        $this->cleanupPackageIds[] = $id;
        return $id;
    }

    private function createSlot(int $packageId, string $date, string $timeFrom, int $seats): int
    {
        // tour_slot_times.tour_slot_id has a NOT NULL FK to tour_slot_dates, so
        // create the parent date row first.
        $dateId = DB::connection('tenant')->table('tour_slot_dates')->insertGetId([
            'tour_package_id' => $packageId,
            'date'            => $date,
            'seats'           => $seats,
            'status'          => 'Active',
            'deleted_by'      => 0,
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now(),
        ]);
        $this->cleanupDateIds[] = $dateId;

        $id = DB::connection('tenant')->table('tour_slot_times')->insertGetId([
            'tour_slot_id'    => $dateId,
            'tour_package_id' => $packageId,
            'date'            => $date,
            'time_from'       => $timeFrom,
            'time_to'         => $timeFrom,
            'time_slot_seats' => $seats,
            'bookable_status' => 'Open',
            'deleted_by'      => 0,
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now(),
        ]);
        $this->cleanupSlotIds[] = $id;
        return $id;
    }

    /** An affiliate customer in the (origin) tenant. */
    private function createAffiliate(array $overrides = []): int
    {
        $operatorId = DB::connection('tenant')->table('tour_operators')->value('id');
        $id = DB::connection('tenant')->table('customers')->insertGetId(array_merge([
            'tour_operator_id' => $operatorId,
            'name'             => 'Partner Origin Affiliate ' . uniqid(),
            'email'            => 'affiliate-' . uniqid() . '@test.com',
            'phone_number'     => '0000000000',
            'is_affiliate'     => 'Yes',
            'status'           => 'Active',
            'created_at'       => Carbon::now(),
            'updated_at'       => Carbon::now(),
        ], $overrides));
        $this->cleanupCustomerIds[] = $id;
        return $id;
    }

    /**
     * An active, standard (non-custom-date) rate for a package + affiliate, keyed
     * to the given date's year. $overrides can set is_infant_rate / rate / etc.
     */
    private function createRate(int $packageId, int $affiliateId, string $date, array $overrides = []): int
    {
        $id = DB::connection('tenant')->table('tour_package_rates')->insertGetId(array_merge([
            'tour_package_id'              => $packageId,
            'affiliate_id'                 => $affiliateId,
            'rate_for'                     => 'Adult',
            'rate'                         => 50,
            'additional_charge'            => 0,
            'permit_fee'                   => 0,
            'processing_charge_percentage' => 0,
            'additional_tax_percentage'    => 0,
            'is_infant_rate'               => 0,
            'is_group_rate'                => 0,
            'status'                       => 'Active',
            'custom_date_id'               => null,
            'scheduling_year'              => (int) substr($date, 0, 4),
            'description'                  => '',
            'orderby'                      => 0,
            'created_at'                   => Carbon::now(),
            'updated_at'                   => Carbon::now(),
        ], $overrides));
        $this->cleanupRateIds[] = $id;
        return $id;
    }

    /**
     * Build a local package plus a partner_cross_tenant_origins row per origin.
     * Each $origins entry: ['origin_package_id'=>int, 'origin_tenant'=>string,
     * 'time_from'=>'HH:MM:SS', 'origin_customer_id'=>int|null,
     * 'local_time_from'=>'HH:MM:SS'|null (departure grouping)].
     */
    private function crossTenantPackage(array $origins): TourPackage
    {
        $id = $this->createPackage();
        foreach ($origins as $o) {
            $mapId = DB::connection('tenant')->table('partner_cross_tenant_origins')->insertGetId([
                'package_id'          => $id,
                'origin_package_id'   => $o['origin_package_id'],
                'origin_tenant'       => $o['origin_tenant'],
                'origin_customer_id'  => $o['origin_customer_id'] ?? null,
                'local_time_from'     => $o['local_time_from'] ?? null,
                'time_from'           => $o['time_from'],
                'time_to'             => $o['time_to'] ?? null,
                'buffer_time_minutes' => $o['buffer_time_minutes'] ?? 0,
                'created_at'          => Carbon::now(),
                'updated_at'          => Carbon::now(),
            ]);
            $this->cleanupOriginMapIds[] = $mapId;
        }
        return TourPackage::find($id);
    }

    private function futureDate(): string
    {
        // Year >= 2025 so TimeSlotAvailabilityService::updateSlotSummary applies.
        return Carbon::now()->addDays(30)->toDateString();
    }

    // ── aggregateVacancies ──────────────────────────────────────────────────────

    public function test_aggregate_vacancies_takes_min_across_origins(): void
    {
        $date = $this->futureDate();
        $pkgA = $this->createPackage();
        $pkgB = $this->createPackage();
        $this->createSlot($pkgA, $date, '09:00:00', 2);
        $this->createSlot($pkgB, $date, '09:00:00', 5);

        $combo = $this->crossTenantPackage([
            ['origin_package_id' => $pkgA, 'origin_tenant' => self::TENANT, 'time_from' => '09:00:00'],
            ['origin_package_id' => $pkgB, 'origin_tenant' => self::TENANT, 'time_from' => '09:00:00'],
        ]);

        $vac = PartnerBookings::aggregateVacancies($combo, $date, $date);

        $this->assertArrayHasKey($date, $vac);
        $this->assertSame(2, $vac[$date], 'Combined vacancy is the minimum across origins.');
    }

    public function test_aggregate_vacancies_combines_origins_at_different_times(): void
    {
        // The real combo case: a boat trip at 09:00 (apm) + an antelope tour at
        // 13:30 (kens). Different times, both available on the same date.
        $date = $this->futureDate();
        $boat     = $this->createPackage();
        $antelope = $this->createPackage();
        $this->createSlot($boat, $date, '09:00:00', 4);
        $this->createSlot($antelope, $date, '13:30:00', 3);

        $combo = $this->crossTenantPackage([
            ['origin_package_id' => $boat,     'origin_tenant' => self::TENANT, 'time_from' => '09:00:00'],
            ['origin_package_id' => $antelope, 'origin_tenant' => self::TENANT, 'time_from' => '13:30:00'],
        ]);

        $vac = PartnerBookings::aggregateVacancies($combo, $date, $date);

        $this->assertArrayHasKey($date, $vac, 'Combo is bookable when each origin has its own-time slot.');
        $this->assertSame(3, $vac[$date], 'Combined vacancy is the minimum across the differently-timed origins.');
    }

    public function test_aggregate_vacancies_absent_when_an_origin_lacks_its_slot(): void
    {
        $date = $this->futureDate();
        $pkgA = $this->createPackage();
        $pkgB = $this->createPackage();
        $this->createSlot($pkgA, $date, '09:00:00', 4);
        $this->createSlot($pkgB, $date, '11:00:00', 4); // B only runs at 11:00

        // B is mapped at 09:00, but B has no 09:00 slot => whole combo unavailable.
        $combo = $this->crossTenantPackage([
            ['origin_package_id' => $pkgA, 'origin_tenant' => self::TENANT, 'time_from' => '09:00:00'],
            ['origin_package_id' => $pkgB, 'origin_tenant' => self::TENANT, 'time_from' => '09:00:00'],
        ]);

        $vac = PartnerBookings::aggregateVacancies($combo, $date, $date);

        $this->assertArrayNotHasKey($date, $vac, 'An origin missing its configured-time slot makes the combo unbookable.');
    }

    // ── confirm / cancel round trip ─────────────────────────────────────────────

    public function test_confirm_creates_origin_booking_and_cancel_restores_seats(): void
    {
        $date      = $this->futureDate();
        $affiliate = $this->createAffiliate();
        $pkg       = $this->createPackage();
        $slot      = $this->createSlot($pkg, $date, '09:00:00', 5);
        $rateId    = $this->createRate($pkg, $affiliate, $date, ['rate' => 50]);

        // Origin priced from the affiliate's own rate.
        $combo = $this->crossTenantPackage([
            ['origin_package_id' => $pkg, 'origin_tenant' => self::TENANT, 'time_from' => '09:00:00', 'origin_customer_id' => $affiliate],
        ]);

        // Local slot time is decorative now; only its date correlates to origins.
        $lcSlot = (object) ['date' => $date, 'time_from' => '07:00:00'];

        $origin = PartnerBookings::confirmOrigins('test-ref-' . uniqid(), $combo, $lcSlot, 2, 0, [
            'firstName'    => 'CrossTenant',
            'lastName'     => 'Tester',
            'emailAddress' => 'crosstenant@test.com',
        ]);

        $this->assertCount(1, $origin);
        $this->cleanupTourBookingIds[] = $origin[0]['tour_booking_id'];

        // The origin now has a real Booked booking consuming 2 seats.
        $booked = (int) DB::connection('tenant')->table('tour_bookings_master')
            ->where('id', $origin[0]['tour_booking_id'])
            ->value('tourists');
        $this->assertSame(2, $booked);

        // Priced at the origin affiliate's own rate (50 x 2), and viewable: a
        // transact row references the origin rate.
        $transact = DB::connection('tenant')->table('tour_bookings_transact')
            ->where('tour_booking_id', $origin[0]['tour_booking_id'])
            ->where('tour_package_rate_id', $rateId)
            ->first();
        $this->assertNotNull($transact, 'Origin booking must have a transact row so it is viewable.');
        $this->assertSame('100.00', (string) $transact->total, 'Line priced at the origin rate (50 x 2).');
        $masterTotal = DB::connection('tenant')->table('tour_bookings_master')
            ->where('id', $origin[0]['tour_booking_id'])->value('total');
        $this->assertSame('100.00', (string) $masterTotal, 'Origin booking total is the origin rate.');

        $activeSeats = (int) DB::connection('tenant')->table('tour_bookings_master')
            ->where('tour_slot_id', $slot)
            ->where('status', '!=', 'Cancelled')
            ->sum('tourists');
        $this->assertSame(2, $activeSeats, 'Origin slot lost 2 seats after confirm.');

        // Cancel reverses it.
        PartnerBookings::cancelOrigins($origin);

        $status = DB::connection('tenant')->table('tour_bookings_master')
            ->where('id', $origin[0]['tour_booking_id'])
            ->value('status');
        $this->assertSame('Cancelled', $status);

        $activeSeatsAfter = (int) DB::connection('tenant')->table('tour_bookings_master')
            ->where('tour_slot_id', $slot)
            ->where('status', '!=', 'Cancelled')
            ->sum('tourists');
        $this->assertSame(0, $activeSeatsAfter, 'Seats restored after cancel.');
    }

    public function test_confirm_fails_and_compensates_when_an_origin_is_short_on_seats(): void
    {
        $date      = $this->futureDate();
        $affiliate = $this->createAffiliate();
        $pkgOk     = $this->createPackage();
        $pkgShort  = $this->createPackage();
        $slotOk    = $this->createSlot($pkgOk, $date, '09:00:00', 10);
        $this->createSlot($pkgShort, $date, '09:00:00', 1); // only 1 seat, need 3
        // pkgOk prices fine (so it books first); pkgShort fails on seats before pricing.
        $this->createRate($pkgOk, $affiliate, $date, ['rate' => 50]);

        $combo = $this->crossTenantPackage([
            ['origin_package_id' => $pkgOk, 'origin_tenant' => self::TENANT, 'time_from' => '09:00:00', 'origin_customer_id' => $affiliate],
            ['origin_package_id' => $pkgShort, 'origin_tenant' => self::TENANT, 'time_from' => '09:00:00', 'origin_customer_id' => $affiliate],
        ]);

        $lcSlot = (object) ['date' => $date, 'time_from' => '09:00:00'];

        $threw = false;
        try {
            PartnerBookings::confirmOrigins('test-ref-' . uniqid(), $combo, $lcSlot, 3, 0, [
                'firstName' => 'X', 'lastName' => 'Y', 'emailAddress' => 'x@y.com',
            ]);
        } catch (\Throwable $e) {
            $threw = true;
        }

        $this->assertTrue($threw, 'Insufficient seats at an origin must throw.');

        // The first origin (pkgOk) must have been compensated: no active booking left.
        $leftover = (int) DB::connection('tenant')->table('tour_bookings_master')
            ->where('tour_slot_id', $slotOk)
            ->where('status', '!=', 'Cancelled')
            ->sum('tourists');
        $this->assertSame(0, $leftover, 'Partial success was compensated — no half-booked combo.');

        // Sweep any rows the (now-cancelled) compensation created, for cleanup.
        $ids = DB::connection('tenant')->table('tour_bookings_master')
            ->whereIn('tour_slot_id', [$slotOk])
            ->pluck('id')->all();
        $this->cleanupTourBookingIds = array_merge($this->cleanupTourBookingIds, $ids);
    }

    // ── pricing guard: origin with no affiliate rate fails cleanly ──────────────

    public function test_confirm_fails_cleanly_when_origin_has_no_rate(): void
    {
        $date      = $this->futureDate();
        $affiliate = $this->createAffiliate();   // affiliate exists...
        $pkg       = $this->createPackage();
        $slot      = $this->createSlot($pkg, $date, '09:00:00', 5);
        // ...but NO rate is created for it.

        $combo = $this->crossTenantPackage([
            ['origin_package_id' => $pkg, 'origin_tenant' => self::TENANT, 'time_from' => '09:00:00', 'origin_customer_id' => $affiliate],
        ]);

        $lcSlot = (object) ['date' => $date, 'time_from' => '09:00:00'];

        $threw = false;
        try {
            PartnerBookings::confirmOrigins('test-ref-' . uniqid(), $combo, $lcSlot, 2, 0, [
                'firstName' => 'No', 'lastName' => 'Rate', 'emailAddress' => 'norate@test.com',
            ]);
        } catch (\Throwable $e) {
            $threw = true;
            $this->assertStringContainsString('not priceable', $e->getMessage());
        }

        $this->assertTrue($threw, 'An origin with no affiliate rate must fail the fan-out.');

        // No un-openable booking left behind (rolled back by the per-origin transaction).
        $leftover = (int) DB::connection('tenant')->table('tour_bookings_master')
            ->where('tour_slot_id', $slot)
            ->count();
        $this->assertSame(0, $leftover, 'No origin booking created when it is not priceable.');
    }

    // ── multi-departure (local_time_from grouping) ──────────────────────────────

    public function test_aggregate_vacancies_are_scoped_by_departure(): void
    {
        // Same two activities, two departures. Boat + antelope at 12:00, and again
        // at 15:00, each leg at its own origin time.
        $date     = $this->futureDate();
        $boat     = $this->createPackage();
        $antelope = $this->createPackage();
        $this->createSlot($boat, $date, '09:00:00', 4);      // 12:00 departure leg
        $this->createSlot($antelope, $date, '11:00:00', 5);  // 12:00 departure leg
        $this->createSlot($boat, $date, '13:00:00', 2);      // 15:00 departure leg
        $this->createSlot($antelope, $date, '15:00:00', 3);  // 15:00 departure leg

        $combo = $this->crossTenantPackage([
            ['origin_package_id' => $boat,     'origin_tenant' => self::TENANT, 'time_from' => '09:00:00', 'local_time_from' => '12:00:00'],
            ['origin_package_id' => $antelope, 'origin_tenant' => self::TENANT, 'time_from' => '11:00:00', 'local_time_from' => '12:00:00'],
            ['origin_package_id' => $boat,     'origin_tenant' => self::TENANT, 'time_from' => '13:00:00', 'local_time_from' => '15:00:00'],
            ['origin_package_id' => $antelope, 'origin_tenant' => self::TENANT, 'time_from' => '15:00:00', 'local_time_from' => '15:00:00'],
        ]);

        $this->assertSame(['12:00', '15:00'], PartnerBookings::departureTimes($combo));

        $noon = PartnerBookings::aggregateVacancies($combo, $date, $date, '12:00:00');
        $this->assertSame(4, $noon[$date], 'Noon departure = min(boat@09:00=4, antelope@11:00=5).');

        $afternoon = PartnerBookings::aggregateVacancies($combo, $date, $date, '15:00:00');
        $this->assertSame(2, $afternoon[$date], 'Afternoon departure = min(boat@13:00=2, antelope@15:00=3).');
    }

    public function test_confirm_books_only_the_matching_departure(): void
    {
        $date      = $this->futureDate();
        $affiliate = $this->createAffiliate();
        $boat      = $this->createPackage();
        $antelope  = $this->createPackage();
        $boatNoon      = $this->createSlot($boat, $date, '09:00:00', 4);
        $antelopeNoon  = $this->createSlot($antelope, $date, '11:00:00', 5);
        $boatPm        = $this->createSlot($boat, $date, '13:00:00', 2);
        $antelopePm    = $this->createSlot($antelope, $date, '15:00:00', 3);
        $this->createRate($boat, $affiliate, $date, ['rate' => 50]);
        $this->createRate($antelope, $affiliate, $date, ['rate' => 60]);

        $combo = $this->crossTenantPackage([
            ['origin_package_id' => $boat,     'origin_tenant' => self::TENANT, 'time_from' => '09:00:00', 'local_time_from' => '12:00:00', 'origin_customer_id' => $affiliate],
            ['origin_package_id' => $antelope, 'origin_tenant' => self::TENANT, 'time_from' => '11:00:00', 'local_time_from' => '12:00:00', 'origin_customer_id' => $affiliate],
            ['origin_package_id' => $boat,     'origin_tenant' => self::TENANT, 'time_from' => '13:00:00', 'local_time_from' => '15:00:00', 'origin_customer_id' => $affiliate],
            ['origin_package_id' => $antelope, 'origin_tenant' => self::TENANT, 'time_from' => '15:00:00', 'local_time_from' => '15:00:00', 'origin_customer_id' => $affiliate],
        ]);

        // Customer booked the 12:00 lc slot.
        $lcSlot = (object) ['date' => $date, 'time_from' => '12:00:00'];
        $origins = PartnerBookings::confirmOrigins('dep-noon-' . uniqid(), $combo, $lcSlot, 2, 0, [
            'firstName' => 'Noon', 'lastName' => 'Guest', 'emailAddress' => 'noon@test.com',
        ]);

        $this->assertCount(2, $origins, 'Only the two 12:00 legs are booked.');
        foreach ($origins as $o) {
            $this->cleanupTourBookingIds[] = $o['tour_booking_id'];
        }

        // The 12:00 legs consumed seats; the 15:00 legs were left untouched.
        $seats = fn ($slot) => (int) DB::connection('tenant')->table('tour_bookings_master')
            ->where('tour_slot_id', $slot)->where('status', '!=', 'Cancelled')->sum('tourists');
        $this->assertSame(2, $seats($boatNoon), 'Boat 09:00 leg booked.');
        $this->assertSame(2, $seats($antelopeNoon), 'Antelope 11:00 leg booked.');
        $this->assertSame(0, $seats($boatPm), 'Boat 13:00 leg NOT booked.');
        $this->assertSame(0, $seats($antelopePm), 'Antelope 15:00 leg NOT booked.');
    }

    public function test_confirm_rejects_a_time_with_no_departure(): void
    {
        $date      = $this->futureDate();
        $affiliate = $this->createAffiliate();
        $boat      = $this->createPackage();
        $this->createSlot($boat, $date, '09:00:00', 4);
        $this->createRate($boat, $affiliate, $date, ['rate' => 50]);

        $combo = $this->crossTenantPackage([
            ['origin_package_id' => $boat, 'origin_tenant' => self::TENANT, 'time_from' => '09:00:00', 'local_time_from' => '12:00:00', 'origin_customer_id' => $affiliate],
        ]);

        // Booked an lc slot at 18:00 — no departure configured for that time.
        $lcSlot = (object) ['date' => $date, 'time_from' => '18:00:00'];
        $threw = false;
        try {
            PartnerBookings::confirmOrigins('dep-bad-' . uniqid(), $combo, $lcSlot, 1, 0, [
                'firstName' => 'X', 'lastName' => 'Y', 'emailAddress' => 'x@y.com',
            ]);
        } catch (\Throwable $e) {
            $threw = true;
            $this->assertStringContainsString('no origins configured for the booked departure', $e->getMessage());
        }
        $this->assertTrue($threw, 'A booked time with no departure must fail the fan-out.');

        $leftover = (int) DB::connection('tenant')->table('tour_bookings_master')
            ->where('tour_package_id', $boat)->count();
        $this->assertSame(0, $leftover, 'No origin booking created for an unconfigured departure.');
    }

    // ── guard: non-cross-tenant package is inert ────────────────────────────────

    public function test_non_cross_tenant_package_is_a_noop(): void
    {
        $plain = TourPackage::find($this->createPackage()); // no origin mapping rows

        $this->assertFalse($plain->isCrossTenantPartnerPackage());
        $this->assertSame([], PartnerBookings::aggregateVacancies($plain, $this->futureDate(), $this->futureDate()));
        $this->assertSame([], PartnerBookings::confirmOrigins('r', $plain, (object) ['date' => $this->futureDate(), 'time_from' => '09:00:00'], 1, 0, []));

        // cancelOrigins([]) must not throw.
        PartnerBookings::cancelOrigins([]);
        $this->addToAssertionCount(1);
    }

    /**
     * The origin booking code's prefix is the only thing the calendar has to resolve
     * the reseller's title and logo from (getTitleFromOtaBookingCode ->
     * getOTALogoByTitle). A hardcoded 'PARTNER-' matches no entry in constants.otas,
     * so both return null and the UI renders <img src="images/otas/"> -- a broken icon.
     * The prefix must come from the origin affiliate's ota_id.
     */
    public function test_origin_booking_code_carries_the_affiliate_ota_prefix(): void
    {
        $date = $this->futureDate();
        // ota_id 'LC' is the alias the Lagunacreek OTA entry matches on.
        $affiliate = $this->createAffiliate(['ota_id' => 'LC']);
        $pkg       = $this->createPackage();
        $this->createSlot($pkg, $date, '09:00:00', 5);
        $this->createRate($pkg, $affiliate, $date, ['rate' => 50]);

        $combo = $this->crossTenantPackage([
            ['origin_package_id' => $pkg, 'origin_tenant' => self::TENANT, 'time_from' => '09:00:00', 'origin_customer_id' => $affiliate],
        ]);

        $lcSlot = (object) ['date' => $date, 'time_from' => '07:00:00'];

        $origin = PartnerBookings::confirmOrigins('test-ref-' . uniqid(), $combo, $lcSlot, 2, 0, [
            'firstName'    => 'OtaPrefix',
            'lastName'     => 'Tester',
            'emailAddress' => 'otaprefix@test.com',
        ]);

        $this->assertCount(1, $origin);
        $this->cleanupTourBookingIds[] = $origin[0]['tour_booking_id'];

        $code = DB::connection('tenant')->table('tour_bookings_master')
            ->where('id', $origin[0]['tour_booking_id'])
            ->value('ota_reference_booking');

        $this->assertStringStartsWith('LC-', (string) $code, "Origin booking code must carry the affiliate's OTA prefix, got '{$code}'.");

        // The prefix is only useful if it survives the round trip the UI actually makes.
        $title = BokunUtils::getTitleFromOtaBookingCode($code);
        $this->assertSame('Lagunacreek', $title, "Code '{$code}' did not resolve to an OTA title; the calendar would render a broken image.");
        $this->assertSame('lagunacreek.png', BokunUtils::getOTALogoByTitle($title));
    }
}
