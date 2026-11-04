<?php

namespace Tests\Unit\Bokun;

use Tests\TestCase;
use App\Repositories\Bokun\BokunRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Integration tests for BokunRepository::bookingReservation.
 *
 * Run with:
 *   docker exec -it nat-app php artisan test tests/Unit/Bokun/BokunBookingReservationTest.php
 */
class BokunBookingReservationTest extends TestCase
{
    protected array $cleanupReservationCodes = [];
    protected array $cleanupPackageIds       = [];
    protected array $cleanupRateIds          = [];
    protected array $cleanupSlotTimeIds      = [];
    protected array $cleanupSlotDateIds      = [];
    protected array $cleanupCustomerIds      = [];
    protected array $cleanupAffSlotConfigIds = [];
    protected array $cleanupAffSlotTimeIds   = [];

    private function createReservationFixture(int $seats = 20, bool $withInfantRate = false, int $otaDuration = 0, int $packageHasSlots = 0): array
    {
        $now         = Carbon::now();
        $tourDate    = Carbon::tomorrow()->addDays(5)->toDateString();
        $timeFrom    = '09:00:00';
        $timeTo      = '10:00:00';
        $year        = Carbon::parse($tourDate)->year;
        $productCode = 'BKRES-' . substr(uniqid(), -6);

        $operatorId = DB::table('tour_operators')->value('id');
        $this->assertNotNull($operatorId, 'Need at least one tour_operator in the DB');

        $packageId = DB::table('tour_packages')->insertGetId([
            'tour_operator_id'              => $operatorId,
            'name'                          => 'Bokun Reservation Test ' . $productCode,
            'bokun_product_code'            => $productCode,
            'duration'                      => 1,
            'ota_duration'                  => $otaDuration,
            'package_has_slots'             => $packageHasSlots,
            'status'                        => 'Active',
            'type'                          => 'Regular',
            'service_commission_percentage' => 0,
            'stripe_commission_percentage'  => 0,
            'created_by'                    => 1,
            'created_by_user_type'          => 'Admin',
            'created_at'                    => $now,
            'updated_at'                    => $now,
        ]);
        $this->cleanupPackageIds[] = $packageId;

        $existingAffiliate = DB::table('customers')
            ->where('tour_operator_id', $operatorId)
            ->where('bokun', 1)
            ->where('is_affiliate', 'Yes')
            ->where('deleted_by', 0)
            ->first();

        if ($existingAffiliate) {
            $customerId = $existingAffiliate->id;
        } else {
            $customerId = DB::table('customers')->insertGetId([
                'tour_operator_id'     => $operatorId,
                'name'                 => 'Bokun Res Test Affiliate',
                'email'                => 'bokun-res-' . uniqid() . '@test.com',
                'phone_number'         => '5550000002',
                'is_affiliate'         => 'Yes',
                'bokun'                => 1,
                'status'               => 'Active',
                'remember_token'       => '',
                'created_by'           => 1,
                'created_by_user_type' => 'Admin',
                'created_at'           => $now,
            ]);
            $this->cleanupCustomerIds[] = $customerId;
        }

        $adultRateId = DB::table('tour_package_rates')->insertGetId([
            'tour_package_id'               => $packageId,
            'affiliate_id'                  => $customerId,
            'rate_for'                      => 'Adult',
            'age_from'                      => 18,
            'age_to'                        => 64,
            'rate'                          => 50.00,
            'primary_rate'                  => 50.00,
            'permit_fee'                    => 10.00,
            'additional_charge'             => 5.00,
            'processing_charge_percentage'  => 0,
            'additional_tax_percentage'     => 0,
            'is_infant_rate'                => 0,
            'scheduling_year'               => $year,
            'status'                        => 'Active',
            'orderby'                       => 1,
            'created_by'                    => 1,
            'created_by_user_type'          => 'Admin',
            'created_at'                    => $now,
            'updated_at'                    => $now,
        ]);
        $this->cleanupRateIds[] = $adultRateId;

        $infantRateId = null;
        if ($withInfantRate) {
            $infantRateId = DB::table('tour_package_rates')->insertGetId([
                'tour_package_id'               => $packageId,
                'affiliate_id'                  => $customerId,
                'rate_for'                      => 'Infant',
                'age_from'                      => 0,
                'age_to'                        => 2,
                'rate'                          => 0.00,
                'primary_rate'                  => 0.00,
                'permit_fee'                    => 0.00,
                'additional_charge'             => 0.00,
                'processing_charge_percentage'  => 0,
                'additional_tax_percentage'     => 0,
                'is_infant_rate'                => 1,
                'scheduling_year'               => $year,
                'status'                        => 'Active',
                'orderby'                       => 2,
                'created_by'                    => 1,
                'created_by_user_type'          => 'Admin',
                'created_at'                    => $now,
                'updated_at'                    => $now,
            ]);
            $this->cleanupRateIds[] = $infantRateId;
        }

        $slotDateId = DB::table('tour_slot_dates')->insertGetId([
            'tour_package_id'      => $packageId,
            'date'                 => $tourDate,
            'seats'                => $seats,
            'status'               => 'Active',
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);
        $this->cleanupSlotDateIds[] = $slotDateId;

        $slotTimeId = DB::table('tour_slot_times')->insertGetId([
            'tour_slot_id'         => $slotDateId,
            'tour_package_id'      => $packageId,
            'date'                 => $tourDate,
            'time_from'            => $timeFrom,
            'time_to'              => $timeTo,
            'time_slot_seats'      => $seats,
            'bookable_status'      => 'Open',
            'hide_slot'            => 0,
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);
        $this->cleanupSlotTimeIds[] = $slotTimeId;

        $affSlotConfigId = DB::table('tour_slot_times_affiliate')->insertGetId([
            'tour_package_id'      => $packageId,
            'time_from'            => $timeFrom,
            'time_to'              => $timeTo,
            'status'               => 'Active',
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
        ]);
        $this->cleanupAffSlotConfigIds[] = $affSlotConfigId;

        $affSlotTimeId = DB::table('affiliate_slot_times')->insertGetId([
            'tour_slot_time_id'    => $affSlotConfigId,
            'affiliate_id'         => $customerId,
            'seat_limit'           => $seats,
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
        ]);
        $this->cleanupAffSlotTimeIds[] = $affSlotTimeId;

        return [
            'productCode'  => $productCode,
            'packageId'    => $packageId,
            'customerId'   => $customerId,
            'adultRateId'  => $adultRateId,
            'infantRateId' => $infantRateId,
            'slotTimeId'   => $slotTimeId,
            'tourDate'     => $tourDate,
            'timeFrom'     => $timeFrom,
            'seats'        => $seats,
        ];
    }

    private function buildReservationRequest(array $fixture, array $passengers): array
    {
        $dateObj = Carbon::parse($fixture['tourDate']);
        $timeObj = Carbon::parse($fixture['timeFrom']);

        return [
            'reservationData' => [
                'productId'    => $fixture['productCode'],
                'date'         => ['year' => $dateObj->year, 'month' => $dateObj->month, 'day' => $dateObj->day],
                'time'         => ['hour' => $timeObj->hour, 'minute' => $timeObj->minute],
                'reservations' => [['rateId' => '1', 'passengers' => $passengers]],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Success path tests
    // -------------------------------------------------------------------------

    public function testReservationSucceeds(): void
    {
        $fixture    = $this->createReservationFixture();
        $repo       = app(BokunRepository::class);
        $passengers = [
            ['pricingCategoryId' => $fixture['adultRateId']],
            ['pricingCategoryId' => $fixture['adultRateId']],
        ];

        $response = $repo->bookingReservation(new Request($this->buildReservationRequest($fixture, $passengers)));
        $data     = $response->getData(true);

        $this->assertArrayHasKey('successfulReservation', $data, 'Reservation should succeed. Response: ' . json_encode($data));

        $code = $data['successfulReservation']['reservationConfirmationCode'];
        $this->cleanupReservationCodes[] = $code;

        $reservation = DB::table('bokun_reservations')->where('reservation_code', $code)->first();
        $this->assertNotNull($reservation);
        $this->assertEquals(2, $reservation->seats, 'Should hold 2 non-infant seats');
    }

    /**
     * Infant passengers must NOT contribute to the seat hold in bokun_reservations.
     * Before fix: seats = 3 (2 adults + 1 infant).
     * After fix:  seats = 2 (infant excluded).
     */
    public function testReservationInfantExcludedFromSeatHold(): void
    {
        $fixture    = $this->createReservationFixture(seats: 20, withInfantRate: true);
        $repo       = app(BokunRepository::class);
        $passengers = [
            ['pricingCategoryId' => $fixture['adultRateId']],
            ['pricingCategoryId' => $fixture['adultRateId']],
            ['pricingCategoryId' => $fixture['infantRateId']],
        ];

        $response = $repo->bookingReservation(new Request($this->buildReservationRequest($fixture, $passengers)));
        $data     = $response->getData(true);

        $this->assertArrayHasKey('successfulReservation', $data, '2 adults + 1 infant reservation should succeed. Response: ' . json_encode($data));

        $code = $data['successfulReservation']['reservationConfirmationCode'];
        $this->cleanupReservationCodes[] = $code;

        $reservation = DB::table('bokun_reservations')->where('reservation_code', $code)->first();
        $this->assertNotNull($reservation);
        $this->assertEquals(2, $reservation->seats, 'Infant must not count toward seat hold — expected 2, not 3');
    }

    /**
     * Slot with 1 seat. Request: 1 adult + 1 infant.
     * Before fix: totalTourists=2 > totalSeats=1 → rejected.
     * After fix:  totalNonInfantPax=1 <= totalSeats=1 → accepted.
     */
    public function testReservationInfantDoesNotConsumeSlotCapacity(): void
    {
        $fixture    = $this->createReservationFixture(seats: 1, withInfantRate: true);
        $repo       = app(BokunRepository::class);
        $passengers = [
            ['pricingCategoryId' => $fixture['adultRateId']],
            ['pricingCategoryId' => $fixture['infantRateId']],
        ];

        $response = $repo->bookingReservation(new Request($this->buildReservationRequest($fixture, $passengers)));
        $data     = $response->getData(true);

        $this->assertArrayHasKey(
            'successfulReservation',
            $data,
            '1 adult + 1 infant on a 1-seat slot should succeed — infant does not consume seat capacity. Response: ' . json_encode($data)
        );

        $code = $data['successfulReservation']['reservationConfirmationCode'];
        $this->cleanupReservationCodes[] = $code;

        $reservation = DB::table('bokun_reservations')->where('reservation_code', $code)->first();
        $this->assertEquals(1, $reservation->seats, 'Seat hold should be 1 (adult only), not 2');
    }

    // -------------------------------------------------------------------------
    // Failure path tests
    // -------------------------------------------------------------------------

    public function testReservationPackageNotFound(): void
    {
        $repo    = app(BokunRepository::class);
        $request = new Request([
            'reservationData' => [
                'productId'    => 'NON-EXISTENT-XYZ',
                'date'         => ['year' => 2026, 'month' => 6, 'day' => 15],
                'time'         => ['hour' => 9, 'minute' => 0],
                'reservations' => [],
            ],
        ]);

        $response = $repo->bookingReservation($request);
        $data     = $response->getData(true);

        $this->assertArrayHasKey('failedReservation', $data);
        $this->assertStringContainsString('does not exist', $data['failedReservation']['reservationError']);
    }

    public function testReservationPastDate(): void
    {
        $fixture = $this->createReservationFixture();
        $repo    = app(BokunRepository::class);
        $past    = Carbon::yesterday();

        $request = new Request([
            'reservationData' => [
                'productId'    => $fixture['productCode'],
                'date'         => ['year' => $past->year, 'month' => $past->month, 'day' => $past->day],
                'time'         => ['hour' => 9, 'minute' => 0],
                'reservations' => [],
            ],
        ]);

        $response = $repo->bookingReservation($request);
        $data     = $response->getData(true);

        $this->assertArrayHasKey('failedReservation', $data);
        $this->assertStringContainsString('past', $data['failedReservation']['reservationError']);
    }

    public function testReservationNoSeatsAvailable(): void
    {
        $fixture    = $this->createReservationFixture(seats: 0);
        $repo       = app(BokunRepository::class);
        $passengers = [['pricingCategoryId' => $fixture['adultRateId']]];

        $response = $repo->bookingReservation(new Request($this->buildReservationRequest($fixture, $passengers)));
        $data     = $response->getData(true);

        $this->assertArrayHasKey('failedReservation', $data);
        $this->assertStringContainsString('No seats', $data['failedReservation']['reservationError']);
    }

    public function testReservationNotEnoughSeats(): void
    {
        $fixture    = $this->createReservationFixture(seats: 2);
        $repo       = app(BokunRepository::class);
        $passengers = array_fill(0, 10, ['pricingCategoryId' => $fixture['adultRateId']]);

        $response = $repo->bookingReservation(new Request($this->buildReservationRequest($fixture, $passengers)));
        $data     = $response->getData(true);

        $this->assertArrayHasKey('failedReservation', $data);
        $this->assertStringContainsString('seat(s) are available', $data['failedReservation']['reservationError']);
    }

    // -------------------------------------------------------------------------
    // OTA cutoff tests
    // -------------------------------------------------------------------------

    /**
     * Slot is 6 days from now; ota_duration = 168 hours (7 days).
     * 6 days < 7-day cutoff → reservation must be blocked.
     */
    public function testReservationBlockedByOtaCutoff(): void
    {
        $fixture    = $this->createReservationFixture(seats: 20, otaDuration: 168, packageHasSlots: 1);
        $repo       = app(BokunRepository::class);
        $passengers = [['pricingCategoryId' => $fixture['adultRateId']]];

        $response = $repo->bookingReservation(new Request($this->buildReservationRequest($fixture, $passengers)));
        $data     = $response->getData(true);

        $this->assertArrayHasKey('failedReservation', $data, 'Slot within OTA cutoff should be rejected. Response: ' . json_encode($data));
        $this->assertStringContainsString('cutoff', $data['failedReservation']['reservationError']);
    }

    /**
     * Slot is 6 days from now; ota_duration = 2 hours.
     * 6 days > 2-hour cutoff → reservation must succeed.
     */
    public function testReservationSucceedsOutsideOtaCutoff(): void
    {
        $fixture    = $this->createReservationFixture(seats: 20, otaDuration: 2, packageHasSlots: 1);
        $repo       = app(BokunRepository::class);
        $passengers = [
            ['pricingCategoryId' => $fixture['adultRateId']],
            ['pricingCategoryId' => $fixture['adultRateId']],
        ];

        $response = $repo->bookingReservation(new Request($this->buildReservationRequest($fixture, $passengers)));
        $data     = $response->getData(true);

        $this->assertArrayHasKey('successfulReservation', $data, 'Slot outside OTA cutoff should be allowed. Response: ' . json_encode($data));

        $code = $data['successfulReservation']['reservationConfirmationCode'];
        $this->cleanupReservationCodes[] = $code;
    }

    /**
     * ota_duration = 0 disables the cutoff entirely regardless of package_has_slots.
     */
    public function testReservationSucceedsWhenOtaDurationIsZero(): void
    {
        $fixture    = $this->createReservationFixture(seats: 20, otaDuration: 0, packageHasSlots: 1);
        $repo       = app(BokunRepository::class);
        $passengers = [['pricingCategoryId' => $fixture['adultRateId']]];

        $response = $repo->bookingReservation(new Request($this->buildReservationRequest($fixture, $passengers)));
        $data     = $response->getData(true);

        $this->assertArrayHasKey('successfulReservation', $data, 'Zero ota_duration should allow any slot. Response: ' . json_encode($data));

        $code = $data['successfulReservation']['reservationConfirmationCode'];
        $this->cleanupReservationCodes[] = $code;
    }

    // -------------------------------------------------------------------------
    // Cleanup — runs after each test while the app (and DB connection) is live
    // -------------------------------------------------------------------------

    protected function tearDown(): void
    {
        // bookingReservation opens DB::beginTransaction() but some early-return paths skip
        // commit/rollback. Roll back any open transaction before cleanup so our deletes
        // are not themselves rolled back when the connection is dropped.
        if (DB::transactionLevel() > 0) {
            try { DB::rollBack(); } catch (\Throwable $e) {}
        }

        $del = function (string $table, string $col, $val): void {
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
                DB::table($table)->where($col, $val)->delete();
            } catch (\Throwable $e) {
                fwrite(STDERR, "[BOKUN-RES CLEANUP] $table.$col=$val : " . $e->getMessage() . "\n");
            }
        };

        foreach ($this->cleanupReservationCodes as $code) { $del('bokun_reservations',       'reservation_code', $code); }
        foreach ($this->cleanupAffSlotTimeIds   as $id)   { $del('affiliate_slot_times',      'id', $id); }
        foreach ($this->cleanupAffSlotConfigIds as $id)   { $del('tour_slot_times_affiliate', 'id', $id); }
        foreach ($this->cleanupSlotTimeIds      as $id)   {
            $del('tour_slot_times',     'id',           $id);
            $del('tour_slot_summaries', 'tour_slot_id', $id);
        }
        foreach ($this->cleanupSlotDateIds as $id) { $del('tour_slot_dates',    'id', $id); }
        foreach ($this->cleanupRateIds     as $id) { $del('tour_package_rates', 'id', $id); }
        foreach ($this->cleanupPackageIds  as $id) { $del('tour_packages',      'id', $id); }
        foreach ($this->cleanupCustomerIds as $id) { $del('customers',          'id', $id); }

        try { DB::statement('SET FOREIGN_KEY_CHECKS=1'); } catch (\Throwable $e) {}

        parent::tearDown();
    }
}
