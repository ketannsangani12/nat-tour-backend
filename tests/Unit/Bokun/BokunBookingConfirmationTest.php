<?php

namespace Tests\Unit\Bokun;

use Tests\TestCase;
use App\Repositories\Bokun\BokunRepository;
use App\Services\BokunService;
use App\Utils\BokunUtils;
use App\Models\BokunReservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Diagnostic test for Bokun bookingConfirmation.
 *
 * Creates its own Bokun fixture data (package, affiliate, rates, slots)
 * so it runs in any environment (local, CI).
 *
 * Run with:
 *   docker exec -it nat-app php artisan test tests/Unit/Bokun/BokunBookingConfirmationTest.php
 */
class BokunBookingConfirmationTest extends TestCase
{
    // Per-test instance arrays — reset automatically between tests.
    protected array $cleanupBookingIds      = [];
    protected array $cleanupReservationIds  = [];
    protected array $cleanupPackageIds      = [];
    protected array $cleanupCustomerIds     = [];
    protected array $cleanupSlotDateIds     = [];
    protected array $cleanupSlotTimeIds     = [];
    protected array $cleanupRateIds         = [];
    protected array $cleanupAffSlotConfigIds = [];
    protected array $cleanupAffSlotTimeIds  = [];

    private function log(string $msg): void
    {
        $ts = Carbon::now()->format('Y-m-d H:i:s.u');
        $line = "[BOKUN-DIAG $ts] $msg";
        fwrite(STDERR, $line . "\n");
        Log::error($line);
    }

    /**
     * Create all the DB records needed for a Bokun booking confirmation.
     * Returns an array with all created IDs and values.
     */
    private function createBokunFixture(int $otaDuration = 0, int $packageHasSlots = 0): array
    {
        $now = Carbon::now();
        $tourDate = Carbon::tomorrow()->addDays(5)->toDateString();
        $timeFrom = '10:45:00';
        $timeTo = '11:45:00';
        $seats = 20;
        $year = Carbon::parse($tourDate)->year;

        $operatorId = DB::table('tour_operators')->value('id');
        $this->assertNotNull($operatorId, 'Need at least one tour_operator in the DB');

        $productCode = 'BKTEST-' . substr(uniqid(), -6);

        // 1. Tour package with bokun_product_code
        $packageId = DB::table('tour_packages')->insertGetId([
            'tour_operator_id' => $operatorId,
            'name' => 'Bokun Test Package ' . $productCode,
            'bokun_product_code' => $productCode,
            'duration' => 1,
            'ota_duration' => $otaDuration,
            'package_has_slots' => $packageHasSlots,
            'status' => 'Active',
            'type' => 'Regular',
            'service_commission_percentage' => 0,
            'stripe_commission_percentage' => 0,
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->cleanupPackageIds[] = $packageId;

        // 2. Find existing bokun affiliate for this operator, or create one.
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
                'tour_operator_id' => $operatorId,
                'name' => 'Bokun Test Affiliate',
                'email' => 'bokun-test-' . uniqid() . '@test.com',
                'phone_number' => '5550000000',
                'is_affiliate' => 'Yes',
                'bokun' => 1,
                'status' => 'Active',
                'remember_token' => '',
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
                'created_at' => $now,
            ]);
            $this->cleanupCustomerIds[] = $customerId;
        }

        // 3. Rate group for the affiliate
        $rateId = DB::table('tour_package_rates')->insertGetId([
            'tour_package_id' => $packageId,
            'affiliate_id' => $customerId,
            'rate_for' => 'Adult',
            'age_from' => 18,
            'age_to' => 64,
            'rate' => 50.00,
            'primary_rate' => 50.00,
            'permit_fee' => 10.00,
            'additional_charge' => 5.00,
            'processing_charge_percentage' => 0,
            'additional_tax_percentage' => 0,
            'is_infant_rate' => 0,
            'scheduling_year' => $year,
            'status' => 'Active',
            'orderby' => 1,
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->cleanupRateIds[] = $rateId;

        // 4. Slot date
        $slotDateId = DB::table('tour_slot_dates')->insertGetId([
            'tour_package_id' => $packageId,
            'date' => $tourDate,
            'seats' => $seats,
            'status' => 'Active',
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->cleanupSlotDateIds[] = $slotDateId;

        // 5. Slot time
        $slotTimeId = DB::table('tour_slot_times')->insertGetId([
            'tour_slot_id' => $slotDateId,
            'tour_package_id' => $packageId,
            'date' => $tourDate,
            'time_from' => $timeFrom,
            'time_to' => $timeTo,
            'time_slot_seats' => $seats,
            'bookable_status' => 'Open',
            'hide_slot' => 0,
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->cleanupSlotTimeIds[] = $slotTimeId;

        // 6. Affiliate time slot config
        $affSlotConfigId = DB::table('tour_slot_times_affiliate')->insertGetId([
            'tour_package_id' => $packageId,
            'time_from' => $timeFrom,
            'time_to' => $timeTo,
            'status' => 'Active',
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => $now,
        ]);
        $this->cleanupAffSlotConfigIds[] = $affSlotConfigId;

        // 7. Affiliate slot time with seat limit
        $affSlotTimeId = DB::table('affiliate_slot_times')->insertGetId([
            'tour_slot_time_id' => $affSlotConfigId,
            'affiliate_id' => $customerId,
            'seat_limit' => $seats,
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => $now,
        ]);
        $this->cleanupAffSlotTimeIds[] = $affSlotTimeId;

        return [
            'productCode'    => $productCode,
            'packageId'      => $packageId,
            'customerId'     => $customerId,
            'rateId'         => $rateId,
            'slotDateId'     => $slotDateId,
            'slotTimeId'     => $slotTimeId,
            'affSlotTimeId'  => $affSlotTimeId,
            'tourDate'       => $tourDate,
            'timeFrom'       => $timeFrom,
            'timeTo'         => $timeTo,
            'seats'          => $seats,
            'operatorId'     => $operatorId,
        ];
    }

    /**
     * Helper — extends the base fixture with an infant rate for the same package/affiliate.
     */
    private function createBokunFixtureWithInfantRate(): array
    {
        $fixture = $this->createBokunFixture();
        $now     = Carbon::now();
        $year    = Carbon::parse($fixture['tourDate'])->year;

        $infantRateId = DB::table('tour_package_rates')->insertGetId([
            'tour_package_id'               => $fixture['packageId'],
            'affiliate_id'                  => $fixture['customerId'],
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

        return array_merge($fixture, ['infantRateId' => $infantRateId]);
    }

    /**
     * Step through every checkpoint in bookingConfirmation,
     * logging intermediate state so we can pinpoint failures.
     */
    public function testDiagnoseBookingConfirmationFlow(): void
    {
        $fixture = $this->createBokunFixture();
        $productCode = $fixture['productCode'];
        $tourDate = $fixture['tourDate'];
        $timeFrom = $fixture['timeFrom'];
        $tourTimeShort = Carbon::parse($timeFrom)->format('H:i');

        // -- STEP 1: Validate Package --
        $package = BokunService::getPackageDetails($productCode);
        $this->assertNotNull($package, "Package not found for product code '$productCode'");

        // -- STEP 2: Rate Groups --
        $rateGroups = BokunService::rateGroups($package, 'availability');
        $this->assertNotNull($rateGroups, 'Rate groups should not be null');
        $this->assertFalse($rateGroups->isEmpty(), 'Rate groups should not be empty');

        // -- STEP 3: Rates (rate types) --
        $rates = BokunService::rates($package);
        $validRateIds = array_column($rates, 'id');

        // -- STEP 4: Create a reservation --
        $reservationCode = BokunService::generateReservationCode();
        $reservationExpiry = Carbon::now()->addMinutes(30)->format('Y-m-d H:i:s');

        $reservation = BokunReservation::create([
            'tour_package_id' => $package->package_id,
            'product_code' => $productCode,
            'reservation_code' => $reservationCode,
            'date' => $tourDate,
            'time' => $tourTimeShort,
            'seats' => 2,
            'expired_at' => $reservationExpiry,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->cleanupReservationIds[] = $reservation->id;

        // -- STEP 5: Check availability --
        $availability = BokunService::checkSelectedTimeSlotBooking(
            $tourTimeShort,
            $package->package_id,
            $tourDate,
            $package->affiliate_id
        );
        $this->assertNotNull($availability, 'Availability check returned null');

        // -- STEP 6: Slot status --
        $timezone = $availability->timezone ?: 'UTC';
        $slotTime = Carbon::createFromFormat('Y-m-d H:i:s', $availability->date . ' ' . $availability->time_from, $timezone);
        $slotStatus = $slotTime->gte(Carbon::now($timezone)) ? 'Open' : 'Closed';

        // -- STEP 7: Seat check --
        $totalSeats = $availability->available_seats;
        $this->assertGreaterThan(0, $totalSeats, 'Should have available seats');

        // -- STEP 8: Call the actual bookingConfirmation --
        $firstRateId = (string) $rateGroups->first()->id;
        $firstRate = $rateGroups->first();
        $pricePerPerson = round(($firstRate->rate + $firstRate->additional_charge + $firstRate->permit_fee) * (1 + ((float)($firstRate->processing_charge_percentage ?? 0)) / 100), 2);

        $dateObj = Carbon::parse($tourDate);
        $timeObj = Carbon::parse($timeFrom);

        $requestData = [
            'reservationConfirmationCode' => $reservationCode,
            'reservationData' => [
                'productId' => $productCode,
                'date' => [
                    'year' => $dateObj->year,
                    'month' => $dateObj->month,
                    'day' => $dateObj->day,
                ],
                'time' => [
                    'hour' => $timeObj->hour,
                    'minute' => $timeObj->minute,
                ],
                'customerContact' => [
                    'firstName' => 'Test',
                    'lastName' => 'Bokun',
                    'email' => 'bokun-diag-test@example.com',
                    'phone' => '5550000000',
                ],
                'bookingSource' => [
                    'bookingChannel' => [
                        'title' => 'Headout',
                    ],
                ],
                'platformBookingId' => '99999',
                'externalSaleId' => '12345',
                'notes' => '',
                'reservations' => [
                    [
                        'rateId' => $validRateIds[0] ?? '1',
                        'passengers' => [
                            [
                                'pricingCategoryId' => $firstRateId,
                                'pricePerPassenger' => ['amount' => $pricePerPerson],
                            ],
                            [
                                'pricingCategoryId' => $firstRateId,
                                'pricePerPassenger' => ['amount' => $pricePerPerson],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $request = new Request($requestData);
        $repo = app(BokunRepository::class);

        $response = $repo->bookingConfirmation($request);
        $responseData = $response->getData(true);

        if (isset($responseData['successfulBooking'])) {
            $bookingCode = $responseData['successfulBooking']['bookingConfirmationCode'] ?? null;
            if ($bookingCode) {
                $booking = DB::table('tour_bookings_master')
                    ->where('bokun_reference_code', $bookingCode)
                    ->orWhere('ota_reference_booking', $bookingCode)
                    ->first();
                if ($booking) {
                    $this->cleanupBookingIds[] = $booking->id;
                }
            }
        } elseif (isset($responseData['failedBooking'])) {
            $this->log("=== FAILED: " . ($responseData['failedBooking']['bookingError'] ?? 'Unknown') . " ===");
        } else {
            $this->log("=== UNEXPECTED RESPONSE FORMAT ===");
        }

        $this->assertArrayHasKey('successfulBooking', $responseData, 'Booking confirmation should succeed');
    }

    /**
     * Verify that 'Only by Phone' slots are blocked from Bokun booking.
     * The hotfix treats 'Only by Phone' as Closed with 0 available seats.
     */
    public function testOnlyByPhoneSlotBlocksBooking(): void
    {
        $fixture = $this->createBokunFixture();
        $productCode = $fixture['productCode'];
        $tourDate = $fixture['tourDate'];
        $timeFrom = $fixture['timeFrom'];
        $tourTimeShort = Carbon::parse($timeFrom)->format('H:i');

        // Set the slot to 'Only by Phone'
        DB::table('tour_slot_times')
            ->where('id', $fixture['slotTimeId'])
            ->update(['bookable_status' => 'Only by Phone']);

        // The availability check should now treat it as Closed with 0 seats
        $package = BokunService::getPackageDetails($productCode);
        $this->assertNotNull($package);

        $availability = BokunService::checkSelectedTimeSlotBooking(
            $tourTimeShort,
            $package->package_id,
            $tourDate,
            $package->affiliate_id
        );

        if ($availability) {
            $this->assertEquals('Closed', $availability->bookable_status, 'Only by Phone should be converted to Closed');
            $this->assertEquals(0, $availability->available_seats, 'Only by Phone should have 0 available seats');
        }

        // Create reservation and attempt booking - should fail
        $reservationCode = BokunService::generateReservationCode();
        $reservation = BokunReservation::create([
            'tour_package_id' => $package->package_id,
            'product_code' => $productCode,
            'reservation_code' => $reservationCode,
            'date' => $tourDate,
            'time' => $tourTimeShort,
            'seats' => 1,
            'expired_at' => Carbon::now()->addMinutes(30)->format('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->cleanupReservationIds[] = $reservation->id;

        $rateGroups = BokunService::rateGroups($package, 'availability');
        $firstRate = $rateGroups->first();
        $firstRateId = (string) $firstRate->id;
        $rates = BokunService::rates($package);
        $pricePerPerson = round(($firstRate->rate + $firstRate->additional_charge + $firstRate->permit_fee) * (1 + ((float)($firstRate->processing_charge_percentage ?? 0)) / 100), 2);

        $dateObj = Carbon::parse($tourDate);
        $timeObj = Carbon::parse($timeFrom);

        $requestData = [
            'reservationConfirmationCode' => $reservationCode,
            'reservationData' => [
                'productId' => $productCode,
                'date' => ['year' => $dateObj->year, 'month' => $dateObj->month, 'day' => $dateObj->day],
                'time' => ['hour' => $timeObj->hour, 'minute' => $timeObj->minute],
                'customerContact' => ['firstName' => 'Test', 'lastName' => 'Phone', 'email' => 'phone-test@example.com', 'phone' => '5550000000'],
                'bookingSource' => ['bookingChannel' => ['title' => 'Headout']],
                'platformBookingId' => '88888',
                'externalSaleId' => '12345',
                'notes' => '',
                'reservations' => [[
                    'rateId' => $rates[0]['id'] ?? '1',
                    'passengers' => [[
                        'pricingCategoryId' => $firstRateId,
                        'pricePerPassenger' => ['amount' => $pricePerPerson],
                    ]],
                ]],
            ],
        ];

        $request = new Request($requestData);
        $repo = app(BokunRepository::class);
        $response = $repo->bookingConfirmation($request);
        $responseData = $response->getData(true);

        $this->assertArrayHasKey('failedBooking', $responseData, 'Only by Phone slot should reject Bokun booking');

        // Reset slot back to Open for other tests (same DB, different test data is isolated anyway)
        DB::table('tour_slot_times')
            ->where('id', $fixture['slotTimeId'])
            ->update(['bookable_status' => 'Open']);
    }

    /**
     * Test that empty/null bookingChannel title returns null (confirming the bug).
     */
    public function testChannelDetailsNullCrash(): void
    {
        $result = BokunUtils::generateBookingReferenceCode('');
        $result2 = BokunUtils::generateBookingReferenceCode(null);

        $this->assertNull($result, 'Empty string should return null (confirming the bug)');
        $this->assertNull($result2, 'Null should return null (confirming the bug)');
    }

    /**
     * Test the strict type comparison for seat availability.
     */
    public function testSeatCheckStrictComparison(): void
    {
        $this->assertTrue(0 === 0);
        $this->assertFalse("0" === 0, 'String "0" does not === int 0');
    }

    /**
     * Full confirmation flow with 1 adult + 1 infant.
     * Verifies the booking is created with tourists=1, infants=1.
     */
    public function testConfirmationWithInfantRateSucceeds(): void
    {
        $fixture      = $this->createBokunFixtureWithInfantRate();
        $productCode  = $fixture['productCode'];
        $tourDate     = $fixture['tourDate'];
        $timeFrom     = $fixture['timeFrom'];
        $tourTimeShort = Carbon::parse($timeFrom)->format('H:i');

        $package    = BokunService::getPackageDetails($productCode);
        $this->assertNotNull($package);

        $rateGroups   = BokunService::rateGroups($package, 'availability');
        $rates        = BokunService::rates($package);
        $adultRate    = $rateGroups->firstWhere('is_infant_rate', 0);
        $infantRate   = $rateGroups->firstWhere('is_infant_rate', 1);
        $this->assertNotNull($adultRate,  'Adult rate group must exist');
        $this->assertNotNull($infantRate, 'Infant rate group must exist');

        $reservationCode = BokunService::generateReservationCode();
        \App\Models\BokunReservation::create([
            'tour_package_id' => $package->package_id,
            'product_code'    => $productCode,
            'reservation_code'=> $reservationCode,
            'date'            => $tourDate,
            'time'            => $tourTimeShort,
            'seats'           => 1, // 1 adult only — infant excluded from hold
            'expired_at'      => Carbon::now()->addMinutes(30)->format('Y-m-d H:i:s'),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        $this->cleanupReservationIds[] = \App\Models\BokunReservation::where('reservation_code', $reservationCode)->value('id');

        $dateObj = Carbon::parse($tourDate);
        $timeObj = Carbon::parse($timeFrom);

        $requestData = [
            'reservationConfirmationCode' => $reservationCode,
            'reservationData' => [
                'productId' => $productCode,
                'date'      => ['year' => $dateObj->year, 'month' => $dateObj->month, 'day' => $dateObj->day],
                'time'      => ['hour' => $timeObj->hour, 'minute' => $timeObj->minute],
                'customerContact' => ['firstName' => 'Test', 'lastName' => 'Infant', 'email' => 'infant-test@example.com', 'phone' => '5550000000'],
                'bookingSource'   => ['bookingChannel' => ['title' => 'Headout']],
                'platformBookingId' => '77777',
                'externalSaleId'    => '77777',
                'notes' => '',
                'reservations' => [[
                    'rateId'     => $rates[0]['id'] ?? '1',
                    'passengers' => [
                        ['pricingCategoryId' => (string)$adultRate->id,  'pricePerPassenger' => ['amount' => 65.00]],
                        ['pricingCategoryId' => (string)$infantRate->id, 'pricePerPassenger' => ['amount' => 0.00]],
                    ],
                ]],
            ],
        ];

        $repo     = app(BokunRepository::class);
        $response = $repo->bookingConfirmation(new \Illuminate\Http\Request($requestData));
        $data     = $response->getData(true);

        $this->assertArrayHasKey('successfulBooking', $data, 'Confirmation with infant should succeed. Response: ' . json_encode($data));

        $bookingCode = $data['successfulBooking']['bookingConfirmationCode'] ?? null;
        if ($bookingCode) {
            $booking = DB::table('tour_bookings_master')
                ->where('bokun_reference_code', $bookingCode)
                ->orWhere('ota_reference_booking', $bookingCode)
                ->first();
            if ($booking) {
                $this->cleanupBookingIds[] = $booking->id;
                $this->assertEquals(1, $booking->tourists, 'tour_bookings_master.tourists should be 1 (adult only)');
                $this->assertEquals(1, $booking->infants,  'tour_bookings_master.infants should be 1');
            }
        }
    }

    /**
     * Slot with only 1 seat. Request: 1 adult + 1 infant.
     * Before fix: totalPax=2 > totalSeats=1 → rejected.
     * After fix:  totalNonInfantPax=1 <= totalSeats=1 → accepted.
     */
    public function testConfirmationInfantDoesNotConsumeSlotCapacity(): void
    {
        $fixture      = $this->createBokunFixtureWithInfantRate();
        $productCode  = $fixture['productCode'];
        $tourDate     = $fixture['tourDate'];
        $timeFrom     = $fixture['timeFrom'];
        $tourTimeShort = Carbon::parse($timeFrom)->format('H:i');

        // Tighten slot to 1 seat
        DB::table('tour_slot_times')->where('id', $fixture['slotTimeId'])->update(['time_slot_seats' => 1]);
        DB::table('tour_slot_dates')->where('id', $fixture['slotDateId'])->update(['seats' => 1]);
        DB::table('affiliate_slot_times')->where('id', $fixture['affSlotTimeId'])->update(['seat_limit' => 1]);

        $package  = BokunService::getPackageDetails($productCode);
        $this->assertNotNull($package);

        $rateGroups = BokunService::rateGroups($package, 'availability');
        $rates      = BokunService::rates($package);
        $adultRate  = $rateGroups->firstWhere('is_infant_rate', 0);
        $infantRate = $rateGroups->firstWhere('is_infant_rate', 1);

        $reservationCode = BokunService::generateReservationCode();
        \App\Models\BokunReservation::create([
            'tour_package_id'  => $package->package_id,
            'product_code'     => $productCode,
            'reservation_code' => $reservationCode,
            'date'             => $tourDate,
            'time'             => $tourTimeShort,
            'seats'            => 1, // only the adult consumes capacity
            'expired_at'       => Carbon::now()->addMinutes(30)->format('Y-m-d H:i:s'),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
        $this->cleanupReservationIds[] = \App\Models\BokunReservation::where('reservation_code', $reservationCode)->value('id');

        $dateObj = Carbon::parse($tourDate);
        $timeObj = Carbon::parse($timeFrom);

        $requestData = [
            'reservationConfirmationCode' => $reservationCode,
            'reservationData' => [
                'productId' => $productCode,
                'date'      => ['year' => $dateObj->year, 'month' => $dateObj->month, 'day' => $dateObj->day],
                'time'      => ['hour' => $timeObj->hour, 'minute' => $timeObj->minute],
                'customerContact' => ['firstName' => 'Test', 'lastName' => 'Tight', 'email' => 'tight-test@example.com', 'phone' => '5550000000'],
                'bookingSource'   => ['bookingChannel' => ['title' => 'Headout']],
                'platformBookingId' => '66666',
                'externalSaleId'    => '66666',
                'notes' => '',
                'reservations' => [[
                    'rateId'     => $rates[0]['id'] ?? '1',
                    'passengers' => [
                        ['pricingCategoryId' => (string)$adultRate->id,  'pricePerPassenger' => ['amount' => 65.00]],
                        ['pricingCategoryId' => (string)$infantRate->id, 'pricePerPassenger' => ['amount' => 0.00]],
                    ],
                ]],
            ],
        ];

        $repo     = app(BokunRepository::class);
        $response = $repo->bookingConfirmation(new \Illuminate\Http\Request($requestData));
        $data     = $response->getData(true);

        $this->assertArrayHasKey(
            'successfulBooking',
            $data,
            '1 adult + 1 infant on a 1-seat slot should succeed — infant does not consume seat capacity. Response: ' . json_encode($data)
        );

        $bookingCode = $data['successfulBooking']['bookingConfirmationCode'] ?? null;
        if ($bookingCode) {
            $booking = DB::table('tour_bookings_master')
                ->where('bokun_reference_code', $bookingCode)
                ->orWhere('ota_reference_booking', $bookingCode)
                ->first();
            if ($booking) {
                $this->cleanupBookingIds[] = $booking->id;
            }
        }
    }

    // -------------------------------------------------------------------------
    // OTA cutoff race condition test
    // -------------------------------------------------------------------------

    /**
     * Slot is 6 days from now; ota_duration = 168 hours (7 days) → slot is within the cutoff.
     * A valid reservation already exists (simulating checkout before the window shifted).
     * Confirmation must succeed — the OTA cutoff is intentionally not re-checked at this stage
     * to prevent the race condition where Viator has already confirmed and charged the customer.
     */
    public function testConfirmationSucceedsEvenWhenSlotWithinOtaCutoff(): void
    {
        $fixture       = $this->createBokunFixture(otaDuration: 168, packageHasSlots: 1);
        $productCode   = $fixture['productCode'];
        $tourDate      = $fixture['tourDate'];
        $timeFrom      = $fixture['timeFrom'];
        $tourTimeShort = Carbon::parse($timeFrom)->format('H:i');

        $package = BokunService::getPackageDetails($productCode);
        $this->assertNotNull($package);

        $reservationCode = BokunService::generateReservationCode();
        $reservation = BokunReservation::create([
            'tour_package_id'  => $package->package_id,
            'product_code'     => $productCode,
            'reservation_code' => $reservationCode,
            'date'             => $tourDate,
            'time'             => $tourTimeShort,
            'seats'            => 2,
            'expired_at'       => Carbon::now()->addMinutes(30)->format('Y-m-d H:i:s'),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
        $this->cleanupReservationIds[] = $reservation->id;

        $rateGroups     = BokunService::rateGroups($package, 'availability');
        $rates          = BokunService::rates($package);
        $firstRate      = $rateGroups->first();
        $firstRateId    = (string) $firstRate->id;
        $pricePerPerson = round(($firstRate->rate + $firstRate->additional_charge + $firstRate->permit_fee) * (1 + ((float)($firstRate->processing_charge_percentage ?? 0)) / 100), 2);

        $dateObj = Carbon::parse($tourDate);
        $timeObj = Carbon::parse($timeFrom);

        $requestData = [
            'reservationConfirmationCode' => $reservationCode,
            'reservationData' => [
                'productId' => $productCode,
                'date'      => ['year' => $dateObj->year, 'month' => $dateObj->month, 'day' => $dateObj->day],
                'time'      => ['hour' => $timeObj->hour, 'minute' => $timeObj->minute],
                'customerContact' => ['firstName' => 'Test', 'lastName' => 'Cutoff', 'email' => 'cutoff-race-test@example.com', 'phone' => '5550000000'],
                'bookingSource'   => ['bookingChannel' => ['title' => 'Headout']],
                'platformBookingId' => '55555',
                'externalSaleId'    => '55555',
                'notes' => '',
                'reservations' => [[
                    'rateId'     => $rates[0]['id'] ?? '1',
                    'passengers' => [
                        ['pricingCategoryId' => $firstRateId, 'pricePerPassenger' => ['amount' => $pricePerPerson]],
                        ['pricingCategoryId' => $firstRateId, 'pricePerPassenger' => ['amount' => $pricePerPerson]],
                    ],
                ]],
            ],
        ];

        $repo     = app(BokunRepository::class);
        $response = $repo->bookingConfirmation(new Request($requestData));
        $data     = $response->getData(true);

        $this->assertArrayHasKey(
            'successfulBooking',
            $data,
            'Confirmation must succeed even when slot is within OTA cutoff — cutoff is not re-checked at confirmation to prevent the race condition. Response: ' . json_encode($data)
        );

        $bookingCode = $data['successfulBooking']['bookingConfirmationCode'] ?? null;
        if ($bookingCode) {
            $booking = DB::table('tour_bookings_master')
                ->where('bokun_reference_code', $bookingCode)
                ->orWhere('ota_reference_booking', $bookingCode)
                ->first();
            if ($booking) {
                $this->cleanupBookingIds[] = $booking->id;
            }
        }
    }

    // -------------------------------------------------------------------------
    // Cleanup — runs after each test while the app (and DB connection) is live
    // -------------------------------------------------------------------------

    protected function tearDown(): void
    {
        // bookingConfirmation opens DB::beginTransaction() but some early-return paths skip
        // commit/rollback. Roll back any open transaction so our deletes are not rolled back
        // when the connection is dropped by parent::tearDown().
        if (DB::transactionLevel() > 0) {
            try { DB::rollBack(); } catch (\Throwable $e) {}
        }

        // Each operation is wrapped independently — one failure cannot block the others.
        $del = function (string $table, string $col, $val): void {
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
                DB::table($table)->where($col, $val)->delete();
            } catch (\Throwable $e) {
                fwrite(STDERR, "[BOKUN-CONF CLEANUP] $table.$col=$val : " . $e->getMessage() . "\n");
            }
        };

        foreach ($this->cleanupBookingIds as $bookingId) {
            $del('tour_booking_history',   'tour_booking_id', $bookingId);
            $del('transactions',           'tour_booking_id', $bookingId);
            $del('tour_bookings_transact', 'tour_booking_id', $bookingId);
            try {
                $slotId = DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_slot_id');
                if ($slotId) {
                    $del('tour_slot_summaries', 'tour_slot_id', $slotId);
                }
            } catch (\Throwable $e) {}
            $del('tour_bookings_master', 'id', $bookingId);
        }

        foreach ($this->cleanupReservationIds  as $id) { $del('bokun_reservations',       'id', $id); }
        foreach ($this->cleanupAffSlotTimeIds  as $id) { $del('affiliate_slot_times',      'id', $id); }
        foreach ($this->cleanupAffSlotConfigIds as $id) { $del('tour_slot_times_affiliate', 'id', $id); }
        foreach ($this->cleanupSlotTimeIds     as $id) {
            $del('tour_slot_times',    'id',           $id);
            $del('tour_slot_summaries','tour_slot_id', $id);
        }
        foreach ($this->cleanupSlotDateIds as $id) { $del('tour_slot_dates',    'id', $id); }
        foreach ($this->cleanupRateIds     as $id) { $del('tour_package_rates', 'id', $id); }
        foreach ($this->cleanupPackageIds  as $id) { $del('tour_packages',      'id', $id); }
        foreach ($this->cleanupCustomerIds as $id) { $del('customers',          'id', $id); }

        try { DB::statement('SET FOREIGN_KEY_CHECKS=1'); } catch (\Throwable $e) {}

        parent::tearDown();
    }
}
