<?php

namespace Tests\Unit\Bokun;

use Tests\TestCase;
use App\Repositories\Bokun\BokunRepository;
use App\Services\BokunService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Integration tests for BokunRepository::bookingAmendment.
 *
 * Creates its own fixture data (package, affiliate, rates, slots, booking)
 * so it runs in any environment (local, CI).
 *
 * Run with:
 *   docker exec -it nat-app php artisan test tests/Unit/Bokun/BokunBookingAmendmentTest.php
 */
class BokunBookingAmendmentTest extends TestCase
{
    protected array $cleanupBookingIds      = [];
    protected array $cleanupTransactIds     = [];
    protected array $cleanupTransactionIds  = [];
    protected array $cleanupSlotTimeIds     = [];
    protected array $cleanupSlotDateIds     = [];
    protected array $cleanupRateIds         = [];
    protected array $cleanupPackageIds      = [];
    protected array $cleanupCustomerIds     = [];
    protected array $cleanupAffSlotConfigIds = [];
    protected array $cleanupAffSlotTimeIds  = [];

    private function log(string $msg): void
    {
        $ts = Carbon::now()->format('Y-m-d H:i:s.u');
        fwrite(STDERR, "[BOKUN-AMEND $ts] $msg\n");
        Log::info("[BOKUN-AMEND] $msg");
    }

    // -------------------------------------------------------------------------
    // Fixture helpers
    // -------------------------------------------------------------------------

    /**
     * Create all DB records needed for a bookingAmendment test.
     *
     * @param int    $existingPax    Tourists on the existing booking / transact row.
     * @param string $bookingStatus  Status of the existing booking ('Booked'|'Cancelled').
     * @param int    $seats          time_slot_seats value on the slot (controls availability).
     *
     * @return array Fixture data keyed by name.
     */
    private function createAmendmentFixture(
        int $existingPax = 2,
        string $bookingStatus = 'Booked',
        int $seats = 20,
        bool $useSecondRateForBooking = false,
        bool $withInfantRate = false,
        int $existingInfants = 0,
        int $otaDuration = 0,
        int $packageHasSlots = 0
    ): array {
        $now         = Carbon::now();
        $tourDate    = Carbon::tomorrow()->addDays(5)->toDateString();
        $timeFrom    = '10:45:00';
        $timeTo      = '11:45:00';
        $year        = Carbon::parse($tourDate)->year;
        $productCode = 'BKAMEND-' . substr(uniqid(), -6);

        $operatorId = DB::table('tour_operators')->value('id');
        $this->assertNotNull($operatorId, 'Need at least one tour_operator in the DB');

        // 1. Tour package
        $packageId = DB::table('tour_packages')->insertGetId([
            'tour_operator_id'              => $operatorId,
            'name'                          => 'Bokun Amendment Test ' . $productCode,
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

        // 2. Affiliate customer (reuse existing Bokun affiliate if possible)
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
                'name'                 => 'Bokun Amendment Test Affiliate',
                'email'                => 'bokun-amend-' . uniqid() . '@test.com',
                'phone_number'         => '5550000001',
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

        // 3. Rate group
        $rateId = DB::table('tour_package_rates')->insertGetId([
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
        $this->cleanupRateIds[] = $rateId;

        // 3b. Second rate group (Child) — used by mixed-pax tests
        $rateId2 = DB::table('tour_package_rates')->insertGetId([
            'tour_package_id'               => $packageId,
            'affiliate_id'                  => $customerId,
            'rate_for'                      => 'Child',
            'age_from'                      => 3,
            'age_to'                        => 17,
            'rate'                          => 30.00,
            'primary_rate'                  => 30.00,
            'permit_fee'                    => 5.00,
            'additional_charge'             => 2.00,
            'processing_charge_percentage'  => 0,
            'additional_tax_percentage'     => 0,
            'is_infant_rate'                => 0,
            'scheduling_year'               => $year,
            'status'                        => 'Active',
            'orderby'                       => 2,
            'created_by'                    => 1,
            'created_by_user_type'          => 'Admin',
            'created_at'                    => $now,
            'updated_at'                    => $now,
        ]);
        $this->cleanupRateIds[] = $rateId2;

        // 3c. Infant rate (optional)
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
                'orderby'                       => 3,
                'created_by'                    => 1,
                'created_by_user_type'          => 'Admin',
                'created_at'                    => $now,
                'updated_at'                    => $now,
            ]);
            $this->cleanupRateIds[] = $infantRateId;
        }

        // 4. Slot date
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

        // 5. Slot time
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

        // 6. Affiliate slot config
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

        // 7. Affiliate slot time with seat limit
        $affSlotTimeId = DB::table('affiliate_slot_times')->insertGetId([
            'tour_slot_time_id'    => $affSlotConfigId,
            'affiliate_id'         => $customerId,
            'seat_limit'           => $seats,
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
        ]);
        $this->cleanupAffSlotTimeIds[] = $affSlotTimeId;

        // 8. Existing booking (HEAD prefix = Headout)
        $otaCode        = 'HEAD-AMEND-' . substr(uniqid(), -6);
        $transactRateId = $useSecondRateForBooking ? $rateId2 : $rateId;
        $transactRate   = $useSecondRateForBooking ? 30.00 : 50.00;
        $transactPermit = $useSecondRateForBooking ? 5.00  : 10.00;
        $transactAddChg = $useSecondRateForBooking ? 2.00  : 5.00;
        $perPerson      = $transactRate + $transactPermit + $transactAddChg;
        $bookingTotal   = $perPerson * max($existingPax, 1);

        $bookingId = DB::table('tour_bookings_master')->insertGetId([
            'ota_reference_booking'    => $otaCode,
            'tour_package_id'          => $packageId,
            'tour_slot_id'             => $slotTimeId,
            'customer_id'              => $customerId,
            'name'                     => 'Test Amendee',
            'email'                    => 'test@bokun-amend.test',
            'phone_number'             => '5550000001',
            'service_commission'       => 0,
            'tourists'                 => $existingPax,
            'infants'                  => 0,
            'comments'                 => '',
            'note'                     => '',
            'mask_required'            => 'Yes',
            'total'                    => $bookingTotal,
            'booking_total'            => $bookingTotal,
            'liability_waiver_signing' => '',
            'status'                   => $bookingStatus,
            'created_by'               => $customerId,
            'created_by_user_type'     => 'Affiliate',
            'created_at'               => $now,
            'updated_at'               => $now,
        ]);
        $this->cleanupBookingIds[] = $bookingId;

        // 9. Booking transact row (represents existing pax)
        if ($existingPax > 0) {
            $transactId = DB::table('tour_bookings_transact')->insertGetId([
                'tour_booking_id'              => $bookingId,
                'tour_package_rate_id'         => $transactRateId,
                'tourists'                     => $existingPax,
                'infants'                      => 0,
                'is_infant_rate'               => 0,
                'rate'                         => $transactRate,
                'additional_charge'            => $transactAddChg,
                'permit_fee'                   => $transactPermit,
                'additional_tax_percentage'    => 0,
                'processing_charge_percentage' => 0,
                'nat_fees_percentage'          => 0,
                'stripe_fees_percentage'       => 0,
                'partner_fees_amount'          => 0,
                'total'                        => $bookingTotal,
                'deleted_by'                   => 0,
                'check_in_status'              => '',
                'created_by'                   => $customerId,
                'created_by_user_type'         => 'Affiliate',
                'updated_by'                   => $customerId,
                'updated_by_user_type'         => 'Affiliate',
                'created_at'                   => $now,
                'updated_at'                   => $now,
            ]);
            $this->cleanupTransactIds[] = $transactId;
        }

        // 9b. Existing infant transact row (optional — needed for delete-infant tests)
        if ($withInfantRate && $infantRateId && $existingInfants > 0) {
            $infantTransactId = DB::table('tour_bookings_transact')->insertGetId([
                'tour_booking_id'              => $bookingId,
                'tour_package_rate_id'         => $infantRateId,
                'tourists'                     => 0,
                'infants'                      => $existingInfants,
                'is_infant_rate'               => 1,
                'rate'                         => 0.00,
                'additional_charge'            => 0.00,
                'permit_fee'                   => 0.00,
                'additional_tax_percentage'    => 0,
                'processing_charge_percentage' => 0,
                'nat_fees_percentage'          => 0,
                'stripe_fees_percentage'       => 0,
                'partner_fees_amount'          => 0,
                'total'                        => 0.00,
                'deleted_by'                   => 0,
                'check_in_status'              => '',
                'created_by'                   => $customerId,
                'created_by_user_type'         => 'Affiliate',
                'updated_by'                   => $customerId,
                'updated_by_user_type'         => 'Affiliate',
                'created_at'                   => $now,
                'updated_at'                   => $now,
            ]);
            $this->cleanupTransactIds[] = $infantTransactId;

            // Also update the master booking to reflect existing infants
            DB::table('tour_bookings_master')->where('id', $bookingId)->update(['infants' => $existingInfants]);
        }

        // 10. Completed transaction (needed for pax-change path)
        $txnId = DB::table('transactions')->insertGetId([
            'tour_booking_id'       => $bookingId,
            'amount'                => $bookingTotal,
            'fee_amount'            => 0,
            'via'                   => 'Headout',
            'response_init'         => '',
            'response_end'          => '',
            'response_end_datetime' => $now,
            'status'                => 'Completed',
            'payment_type'          => 0,
            'visitor'               => '127.0.0.1',
            'request_from'          => 'HEADOUT',
            'created_by'            => $customerId,
            'created_by_user_type'  => 'Affiliate',
            'created_at'            => $now,
        ]);
        $this->cleanupTransactionIds[] = $txnId;

        return [
            'productCode'    => $productCode,
            'packageId'      => $packageId,
            'customerId'     => $customerId,
            'rateId'         => $rateId,
            'rateId2'        => $rateId2,
            'infantRateId'   => $infantRateId,
            'slotTimeId'     => $slotTimeId,
            'slotDateId'     => $slotDateId,
            'bookingId'      => $bookingId,
            'otaCode'        => $otaCode,
            'tourDate'       => $tourDate,
            'timeFrom'       => $timeFrom,
            'seats'          => $seats,
            'existingPax'    => $existingPax,
            'existingInfants'=> $existingInfants,
        ];
    }

    /**
     * Build a standard amendment request payload.
     *
     * @param array  $fixture      Fixture returned by createAmendmentFixture().
     * @param int    $paxCount     Number of passengers to send in the request.
     * @param string $channel      Booking channel title (must match a configured OTA).
     */
    private function buildAmendmentRequest(array $fixture, ?int $paxCount = null, string $channel = 'Headout'): array
    {
        $paxCount = $paxCount ?? $fixture['existingPax'];
        $dateObj  = Carbon::parse($fixture['tourDate']);
        $timeObj  = Carbon::parse($fixture['timeFrom']);

        $packageObj = (object)[
            'package_id'         => $fixture['packageId'],
            'bokun_product_code' => $fixture['productCode'],
            'affiliate_id'       => $fixture['customerId'],
        ];
        $rateGroups = BokunService::rateGroups($packageObj, 'availability');
        $firstRate  = $rateGroups->first();
        $rateId     = (string) $firstRate->id;

        $passengers = [];
        for ($i = 0; $i < $paxCount; $i++) {
            $passengers[] = [
                'pricingCategoryId' => $rateId,
                'pricePerPassenger' => ['amount' => 65.00],
            ];
        }

        return [
            'bookingConfirmationCode' => $fixture['otaCode'],
            'reservationData' => [
                'productId'     => $fixture['productCode'],
                'date'          => ['year' => $dateObj->year, 'month' => $dateObj->month, 'day' => $dateObj->day],
                'time'          => ['hour' => $timeObj->hour, 'minute' => $timeObj->minute],
                'bookingSource' => ['bookingChannel' => ['title' => $channel]],
                'reservations'  => [['rateId' => '1', 'passengers' => $passengers]],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Failure path tests
    // -------------------------------------------------------------------------

    /**
     * No booking exists for the given confirmation code.
     */
    public function testAmendmentBookingNotFound(): void
    {
        $repo    = app(BokunRepository::class);
        $request = new Request([
            'bookingConfirmationCode' => 'HEAD-NON-EXISTENT-999999',
            'reservationData' => [
                'productId'     => 'ANY-PRODUCT',
                'date'          => ['year' => 2026, 'month' => 6, 'day' => 15],
                'time'          => ['hour' => 10, 'minute' => 0],
                'bookingSource' => ['bookingChannel' => ['title' => 'Headout']],
                'reservations'  => [],
            ],
        ]);

        $response = $repo->bookingAmendment($request);
        $data     = $response->getData(true);

        $this->assertArrayHasKey('failedAmendment', $data);
        $this->assertStringContainsString('Booking not found', $data['failedAmendment']['amendmentError']);
    }

    /**
     * Cannot amend a booking that has already been cancelled.
     */
    public function testAmendmentCancelledBooking(): void
    {
        $fixture = $this->createAmendmentFixture(existingPax: 2, bookingStatus: 'Cancelled');
        $repo    = app(BokunRepository::class);
        $request = new Request($this->buildAmendmentRequest($fixture));

        $response = $repo->bookingAmendment($request);
        $data     = $response->getData(true);

        $this->assertArrayHasKey('failedAmendment', $data);
        $this->assertStringContainsString('Cannot amend a cancelled booking', $data['failedAmendment']['amendmentError']);
    }

    /**
     * The product code in the amendment payload does not exist.
     */
    public function testAmendmentPackageNotFound(): void
    {
        $fixture = $this->createAmendmentFixture();
        $repo    = app(BokunRepository::class);
        $dateObj = Carbon::tomorrow()->addDays(5);

        $request = new Request([
            'bookingConfirmationCode' => $fixture['otaCode'],
            'reservationData' => [
                'productId'     => 'NON-EXISTENT-PRODUCT-XYZ',
                'date'          => ['year' => $dateObj->year, 'month' => $dateObj->month, 'day' => $dateObj->day],
                'time'          => ['hour' => 10, 'minute' => 0],
                'bookingSource' => ['bookingChannel' => ['title' => 'Headout']],
                'reservations'  => [],
            ],
        ]);

        $response = $repo->bookingAmendment($request);
        $data     = $response->getData(true);

        $this->assertArrayHasKey('failedAmendment', $data);
        $this->assertStringContainsString('product does not exist', $data['failedAmendment']['amendmentError']);
    }

    /**
     * The requested travel date is in the past.
     */
    public function testAmendmentPastDate(): void
    {
        $fixture = $this->createAmendmentFixture();
        $repo    = app(BokunRepository::class);
        $past    = Carbon::yesterday();

        $request = new Request([
            'bookingConfirmationCode' => $fixture['otaCode'],
            'reservationData' => [
                'productId'     => $fixture['productCode'],
                'date'          => ['year' => $past->year, 'month' => $past->month, 'day' => $past->day],
                'time'          => ['hour' => 10, 'minute' => 0],
                'bookingSource' => ['bookingChannel' => ['title' => 'Headout']],
                'reservations'  => [],
            ],
        ]);

        $response = $repo->bookingAmendment($request);
        $data     = $response->getData(true);

        $this->assertArrayHasKey('failedAmendment', $data);
        $this->assertStringContainsString('past', $data['failedAmendment']['amendmentError']);
    }

    /**
     * The requested time slot does not exist for the package.
     */
    public function testAmendmentSlotUnavailable(): void
    {
        $fixture = $this->createAmendmentFixture();
        $repo    = app(BokunRepository::class);
        $dateObj = Carbon::parse($fixture['tourDate']);

        // Request a time that has no slot configured (23:59 is unlikely to have a slot)
        $request = new Request([
            'bookingConfirmationCode' => $fixture['otaCode'],
            'reservationData' => [
                'productId'     => $fixture['productCode'],
                'date'          => ['year' => $dateObj->year, 'month' => $dateObj->month, 'day' => $dateObj->day],
                'time'          => ['hour' => 23, 'minute' => 59],
                'bookingSource' => ['bookingChannel' => ['title' => 'Headout']],
                'reservations'  => [],
            ],
        ]);

        $response = $repo->bookingAmendment($request);
        $data     = $response->getData(true);

        $this->assertArrayHasKey('failedAmendment', $data);
        $this->assertStringContainsString('unavailable', $data['failedAmendment']['amendmentError']);
    }

    /**
     * The selected time slot exists but is in the past (Closed status).
     */
    public function testAmendmentSlotClosed(): void
    {
        $fixture = $this->createAmendmentFixture();
        $now     = Carbon::now();

        // Create a slot for today at 00:01 — it will be in the past once the day starts
        $todayDate  = Carbon::today()->toDateString();
        $pastTime   = '00:01:00';

        $closedSlotDateId = DB::table('tour_slot_dates')->insertGetId([
            'tour_package_id'      => $fixture['packageId'],
            'date'                 => $todayDate,
            'seats'                => 10,
            'status'               => 'Active',
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);
        $this->cleanupSlotDateIds[] = $closedSlotDateId;

        $closedSlotTimeId = DB::table('tour_slot_times')->insertGetId([
            'tour_slot_id'         => $closedSlotDateId,
            'tour_package_id'      => $fixture['packageId'],
            'date'                 => $todayDate,
            'time_from'            => $pastTime,
            'time_to'              => '00:02:00',
            'time_slot_seats'      => 10,
            'bookable_status'      => 'Open',
            'hide_slot'            => 0,
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);
        $this->cleanupSlotTimeIds[] = $closedSlotTimeId;

        $repo    = app(BokunRepository::class);
        $todayObj = Carbon::today();

        $request = new Request([
            'bookingConfirmationCode' => $fixture['otaCode'],
            'reservationData' => [
                'productId'     => $fixture['productCode'],
                'date'          => ['year' => $todayObj->year, 'month' => $todayObj->month, 'day' => $todayObj->day],
                'time'          => ['hour' => 0, 'minute' => 1],
                'bookingSource' => ['bookingChannel' => ['title' => 'Headout']],
                'reservations'  => [],
            ],
        ]);

        $response = $repo->bookingAmendment($request);
        $data     = $response->getData(true);

        $this->assertArrayHasKey('failedAmendment', $data);
        $error = $data['failedAmendment']['amendmentError'];
        // Either the slot query returns null (unavailable) or the slot is open but time has passed (Closed)
        $this->assertTrue(
            str_contains($error, 'unavailable') || str_contains($error, 'no longer available'),
            "Expected unavailable/closed error but got: $error"
        );
    }

    /**
     * Adding new pax when the slot has zero available seats.
     */
    public function testAmendmentNoSeatsAvailableWhenAddingPax(): void
    {
        // Fixture with 0 seat capacity and 0 existing tourists
        $fixture = $this->createAmendmentFixture(existingPax: 0, seats: 0);
        $repo    = app(BokunRepository::class);
        $dateObj = Carbon::parse($fixture['tourDate']);
        $timeObj = Carbon::parse($fixture['timeFrom']);

        $packageObj = (object)[
            'package_id'         => $fixture['packageId'],
            'bokun_product_code' => $fixture['productCode'],
            'affiliate_id'       => $fixture['customerId'],
        ];
        $rateGroups = BokunService::rateGroups($packageObj, 'availability');
        $rateId     = (string) $rateGroups->first()->id;

        // Request 1 pax (net new = 1, but available = 0)
        $request = new Request([
            'bookingConfirmationCode' => $fixture['otaCode'],
            'reservationData' => [
                'productId'     => $fixture['productCode'],
                'date'          => ['year' => $dateObj->year, 'month' => $dateObj->month, 'day' => $dateObj->day],
                'time'          => ['hour' => $timeObj->hour, 'minute' => $timeObj->minute],
                'bookingSource' => ['bookingChannel' => ['title' => 'Headout']],
                'reservations'  => [[
                    'rateId'     => '1',
                    'passengers' => [['pricingCategoryId' => $rateId, 'pricePerPassenger' => ['amount' => 65.00]]],
                ]],
            ],
        ]);

        $response = $repo->bookingAmendment($request);
        $data     = $response->getData(true);

        $this->assertArrayHasKey('failedAmendment', $data);
        $this->assertStringContainsString('No seats are available', $data['failedAmendment']['amendmentError']);
    }

    /**
     * Requesting more new pax than the slot has available seats.
     */
    public function testAmendmentNotEnoughSeats(): void
    {
        // Fixture with 2 seats and 0 existing tourists in the booking
        $fixture = $this->createAmendmentFixture(existingPax: 0, seats: 2);
        $repo    = app(BokunRepository::class);
        $dateObj = Carbon::parse($fixture['tourDate']);
        $timeObj = Carbon::parse($fixture['timeFrom']);

        $packageObj = (object)[
            'package_id'         => $fixture['packageId'],
            'bokun_product_code' => $fixture['productCode'],
            'affiliate_id'       => $fixture['customerId'],
        ];
        $rateGroups = BokunService::rateGroups($packageObj, 'availability');
        $rateId     = (string) $rateGroups->first()->id;

        // Request 10 pax (net new = 10, but only 2 seats)
        $passengers = array_fill(0, 10, ['pricingCategoryId' => $rateId, 'pricePerPassenger' => ['amount' => 65.00]]);

        $request = new Request([
            'bookingConfirmationCode' => $fixture['otaCode'],
            'reservationData' => [
                'productId'     => $fixture['productCode'],
                'date'          => ['year' => $dateObj->year, 'month' => $dateObj->month, 'day' => $dateObj->day],
                'time'          => ['hour' => $timeObj->hour, 'minute' => $timeObj->minute],
                'bookingSource' => ['bookingChannel' => ['title' => 'Headout']],
                'reservations'  => [['rateId' => '1', 'passengers' => $passengers]],
            ],
        ]);

        $response = $repo->bookingAmendment($request);
        $data     = $response->getData(true);

        $this->assertArrayHasKey('failedAmendment', $data);
        $this->assertStringContainsString('seat(s) are available', $data['failedAmendment']['amendmentError']);
    }

    // -------------------------------------------------------------------------
    // Success path tests
    // -------------------------------------------------------------------------

    /**
     * When nothing changed (same slot, same pax count), the method returns
     * successfulAmendment immediately without touching the DB.
     */
    public function testAmendmentNoChangeReturnsSuccessEarly(): void
    {
        $fixture = $this->createAmendmentFixture(existingPax: 2);
        $repo    = app(BokunRepository::class);

        // Same pax count as the existing booking → no change
        $request = new Request($this->buildAmendmentRequest($fixture, paxCount: 2));

        $response = $repo->bookingAmendment($request);
        $data     = $response->getData(true);

        $this->assertArrayHasKey('successfulAmendment', $data);
        $this->assertEquals($fixture['otaCode'], $data['successfulAmendment']['amendmentConfirmationCode']);
        $this->assertArrayHasKey('bookingTicket', $data['successfulAmendment']);
        $this->assertArrayHasKey('qrTicket', $data['successfulAmendment']['bookingTicket']);
    }

    /**
     * Adding one extra passenger to an existing booking.
     */
    public function testAmendmentAddPax(): void
    {
        $fixture = $this->createAmendmentFixture(existingPax: 2);
        $repo    = app(BokunRepository::class);

        // Increase from 2 to 3 tourists
        $request = new Request($this->buildAmendmentRequest($fixture, paxCount: 3));

        $response = $repo->bookingAmendment($request);
        $data     = $response->getData(true);

        $this->assertArrayHasKey(
            'successfulAmendment',
            $data,
            'Add-pax amendment should succeed. Response: ' . json_encode($data)
        );
        $this->assertEquals($fixture['otaCode'], $data['successfulAmendment']['amendmentConfirmationCode']);

        // Booking tourists count should be updated to 3
        $updatedBooking = DB::table('tour_bookings_master')->where('id', $fixture['bookingId'])->first();
        $this->assertEquals(3, $updatedBooking->tourists, 'Booking tourists should be updated to 3');
    }

    /**
     * Removing one passenger from an existing booking.
     */
    public function testAmendmentDeletePax(): void
    {
        $fixture = $this->createAmendmentFixture(existingPax: 3);
        $repo    = app(BokunRepository::class);

        // Reduce from 3 to 2 tourists
        $request = new Request($this->buildAmendmentRequest($fixture, paxCount: 2));

        $response = $repo->bookingAmendment($request);
        $data     = $response->getData(true);

        $this->assertArrayHasKey(
            'successfulAmendment',
            $data,
            'Delete-pax amendment should succeed. Response: ' . json_encode($data)
        );
        $this->assertEquals($fixture['otaCode'], $data['successfulAmendment']['amendmentConfirmationCode']);

        // Booking tourists count should be reduced to 2
        $updatedBooking = DB::table('tour_bookings_master')->where('id', $fixture['bookingId'])->first();
        $this->assertEquals(2, $updatedBooking->tourists, 'Booking tourists should be reduced to 2');
    }

    /**
     * Rescheduling a booking to a new slot that does NOT have enough capacity
     * for all existing pax must be rejected — even when pax count is unchanged.
     *
     * Bug: netNewPax was 0 (no pax change), so the old check let it through.
     */
    public function testAmendmentRescheduleBlockedByInsufficientSeats(): void
    {
        // Booking has 3 pax; new slot has only 1 seat
        $fixture = $this->createAmendmentFixture(existingPax: 3, seats: 20);
        $repo    = app(BokunRepository::class);
        $now     = Carbon::now();

        $tightSlotDateId = DB::table('tour_slot_dates')->insertGetId([
            'tour_package_id'      => $fixture['packageId'],
            'date'                 => $fixture['tourDate'],
            'seats'                => 1,
            'status'               => 'Active',
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);
        $this->cleanupSlotDateIds[] = $tightSlotDateId;

        $tightTime = '15:30:00';
        $tightSlotTimeId = DB::table('tour_slot_times')->insertGetId([
            'tour_slot_id'         => $tightSlotDateId,
            'tour_package_id'      => $fixture['packageId'],
            'date'                 => $fixture['tourDate'],
            'time_from'            => $tightTime,
            'time_to'              => '16:30:00',
            'time_slot_seats'      => 1,
            'bookable_status'      => 'Open',
            'hide_slot'            => 0,
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);
        $this->cleanupSlotTimeIds[] = $tightSlotTimeId;

        $tightAffSlotConfigId = DB::table('tour_slot_times_affiliate')->insertGetId([
            'tour_package_id'      => $fixture['packageId'],
            'time_from'            => $tightTime,
            'time_to'              => '16:30:00',
            'status'               => 'Active',
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
        ]);
        $this->cleanupAffSlotConfigIds[] = $tightAffSlotConfigId;

        $tightAffSlotTimeId = DB::table('affiliate_slot_times')->insertGetId([
            'tour_slot_time_id'    => $tightAffSlotConfigId,
            'affiliate_id'         => $fixture['customerId'],
            'seat_limit'           => 1,
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
        ]);
        $this->cleanupAffSlotTimeIds[] = $tightAffSlotTimeId;

        $packageObj = (object)[
            'package_id'         => $fixture['packageId'],
            'bokun_product_code' => $fixture['productCode'],
            'affiliate_id'       => $fixture['customerId'],
        ];
        $rateGroups = BokunService::rateGroups($packageObj, 'availability');
        $rateId     = (string) $rateGroups->first()->id;

        $passengers = array_fill(0, 3, ['pricingCategoryId' => $rateId, 'pricePerPassenger' => ['amount' => 65.00]]);
        $dateObj    = Carbon::parse($fixture['tourDate']);
        $timeObj    = Carbon::parse($tightTime);

        $request = new Request([
            'bookingConfirmationCode' => $fixture['otaCode'],
            'reservationData' => [
                'productId'     => $fixture['productCode'],
                'date'          => ['year' => $dateObj->year, 'month' => $dateObj->month, 'day' => $dateObj->day],
                'time'          => ['hour' => $timeObj->hour, 'minute' => $timeObj->minute],
                'bookingSource' => ['bookingChannel' => ['title' => 'Headout']],
                'reservations'  => [['rateId' => '1', 'passengers' => $passengers]],
            ],
        ]);

        $response = $repo->bookingAmendment($request);
        $data     = $response->getData(true);

        $this->assertArrayHasKey(
            'failedAmendment',
            $data,
            'Reschedule to a slot with only 1 seat should be rejected for a 3-pax booking. Response: ' . json_encode($data)
        );
        $this->assertTrue(
            str_contains($data['failedAmendment']['amendmentError'], 'seat') ||
            str_contains($data['failedAmendment']['amendmentError'], 'No seats'),
            'Error should mention seat availability. Got: ' . $data['failedAmendment']['amendmentError']
        );
    }

    /**
     * Rescheduling the booking to a different time slot on the same date.
     */
    public function testAmendmentSlotChange(): void
    {
        $fixture = $this->createAmendmentFixture(existingPax: 2);
        $repo    = app(BokunRepository::class);
        $now     = Carbon::now();

        // Create a second slot at a different time on the same date
        $newTime = '14:00:00';

        $newSlotDateId = DB::table('tour_slot_dates')->insertGetId([
            'tour_package_id'      => $fixture['packageId'],
            'date'                 => $fixture['tourDate'],
            'seats'                => 20,
            'status'               => 'Active',
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);
        $this->cleanupSlotDateIds[] = $newSlotDateId;

        $newSlotTimeId = DB::table('tour_slot_times')->insertGetId([
            'tour_slot_id'         => $newSlotDateId,
            'tour_package_id'      => $fixture['packageId'],
            'date'                 => $fixture['tourDate'],
            'time_from'            => $newTime,
            'time_to'              => '15:00:00',
            'time_slot_seats'      => 20,
            'bookable_status'      => 'Open',
            'hide_slot'            => 0,
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);
        $this->cleanupSlotTimeIds[] = $newSlotTimeId;

        // Affiliate slot config for the new 14:00 slot — required by reschedule()'s affiliate seat check
        $newAffSlotConfigId = DB::table('tour_slot_times_affiliate')->insertGetId([
            'tour_package_id'      => $fixture['packageId'],
            'time_from'            => $newTime,
            'time_to'              => '15:00:00',
            'status'               => 'Active',
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
        ]);
        $this->cleanupAffSlotConfigIds[] = $newAffSlotConfigId;

        $newAffSlotTimeId = DB::table('affiliate_slot_times')->insertGetId([
            'tour_slot_time_id'    => $newAffSlotConfigId,
            'affiliate_id'         => $fixture['customerId'],
            'seat_limit'           => 20,
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
        ]);
        $this->cleanupAffSlotTimeIds[] = $newAffSlotTimeId;

        $dateObj    = Carbon::parse($fixture['tourDate']);
        $timeObj    = Carbon::parse($newTime);
        $packageObj = (object)[
            'package_id'         => $fixture['packageId'],
            'bokun_product_code' => $fixture['productCode'],
            'affiliate_id'       => $fixture['customerId'],
        ];
        $rateGroups = BokunService::rateGroups($packageObj, 'availability');
        $rateId     = (string) $rateGroups->first()->id;

        $passengers = [
            ['pricingCategoryId' => $rateId, 'pricePerPassenger' => ['amount' => 65.00]],
            ['pricingCategoryId' => $rateId, 'pricePerPassenger' => ['amount' => 65.00]],
        ];

        $request = new Request([
            'bookingConfirmationCode' => $fixture['otaCode'],
            'reservationData' => [
                'productId'     => $fixture['productCode'],
                'date'          => ['year' => $dateObj->year, 'month' => $dateObj->month, 'day' => $dateObj->day],
                'time'          => ['hour' => $timeObj->hour, 'minute' => $timeObj->minute],
                'bookingSource' => ['bookingChannel' => ['title' => 'Headout']],
                'reservations'  => [['rateId' => '1', 'passengers' => $passengers]],
            ],
        ]);

        $response = $repo->bookingAmendment($request);
        $data     = $response->getData(true);

        $this->assertArrayHasKey(
            'successfulAmendment',
            $data,
            'Slot-change amendment should succeed. Response: ' . json_encode($data)
        );
        $this->assertEquals($fixture['otaCode'], $data['successfulAmendment']['amendmentConfirmationCode']);

        // Booking should now point to the new slot
        $updatedBooking = DB::table('tour_bookings_master')->where('id', $fixture['bookingId'])->first();
        $this->assertEquals($newSlotTimeId, $updatedBooking->tour_slot_id, 'Booking should be rescheduled to the new slot');
    }

    /**
     * Mixed pax change where the net result is a refund (delete > add in cost).
     *
     * Existing booking: 2 Adults ($65/each).
     * Amendment:        1 Adult + 1 Child ($37/each).
     * deletedPax = {rateId: 1}  (+$65 refund)
     * addedPax   = {rateId2: 1} (+$37 charge)
     * netAmount  = $37 − $65 = −$28  → triggers the new "Partial Refund" block.
     */
    public function testAmendmentMixedPaxNetRefund(): void
    {
        $fixture = $this->createAmendmentFixture(existingPax: 2);
        $repo    = app(BokunRepository::class);
        $dateObj = Carbon::parse($fixture['tourDate']);
        $timeObj = Carbon::parse($fixture['timeFrom']);

        $request = new Request([
            'bookingConfirmationCode' => $fixture['otaCode'],
            'reservationData' => [
                'productId'     => $fixture['productCode'],
                'date'          => ['year' => $dateObj->year, 'month' => $dateObj->month, 'day' => $dateObj->day],
                'time'          => ['hour' => $timeObj->hour, 'minute' => $timeObj->minute],
                'bookingSource' => ['bookingChannel' => ['title' => 'Headout']],
                'reservations'  => [[
                    'rateId'     => '1',
                    'passengers' => [
                        ['pricingCategoryId' => (string)$fixture['rateId'],  'pricePerPassenger' => ['amount' => 65.00]],
                        ['pricingCategoryId' => (string)$fixture['rateId2'], 'pricePerPassenger' => ['amount' => 37.00]],
                    ],
                ]],
            ],
        ]);

        $response = $repo->bookingAmendment($request);
        $data     = $response->getData(true);

        $this->assertArrayHasKey(
            'successfulAmendment',
            $data,
            'Mixed pax net-refund amendment should succeed. Response: ' . json_encode($data)
        );
        $this->assertEquals($fixture['otaCode'], $data['successfulAmendment']['amendmentConfirmationCode']);

        // 2 Adults → 1 Adult + 1 Child: net tourists = 2
        $updatedBooking = DB::table('tour_bookings_master')->where('id', $fixture['bookingId'])->first();
        $this->assertEquals(2, $updatedBooking->tourists, 'Booking tourist count should remain 2 after mixed net-refund amendment');
    }

    /**
     * Mixed pax change where the net result is a payment (add > delete in cost).
     *
     * Existing booking: 2 Childs ($37/each, via useSecondRateForBooking).
     * Amendment:        3 Adults + 0 Childs.
     * deletedPax = {rateId2: 2}  ($37×2 = $74 refund)
     * addedPax   = {rateId:  3}  ($65×3 = $195 charge)
     * netAmount  = $195 − $74 = +$121  → triggers the payment history path with mixed paymenttotal/refundtotal.
     */
    public function testAmendmentMixedPaxNetPayment(): void
    {
        $fixture = $this->createAmendmentFixture(existingPax: 2, useSecondRateForBooking: true);
        $repo    = app(BokunRepository::class);
        $dateObj = Carbon::parse($fixture['tourDate']);
        $timeObj = Carbon::parse($fixture['timeFrom']);

        $passengers = array_fill(0, 3, [
            'pricingCategoryId' => (string)$fixture['rateId'],
            'pricePerPassenger' => ['amount' => 65.00],
        ]);

        $request = new Request([
            'bookingConfirmationCode' => $fixture['otaCode'],
            'reservationData' => [
                'productId'     => $fixture['productCode'],
                'date'          => ['year' => $dateObj->year, 'month' => $dateObj->month, 'day' => $dateObj->day],
                'time'          => ['hour' => $timeObj->hour, 'minute' => $timeObj->minute],
                'bookingSource' => ['bookingChannel' => ['title' => 'Headout']],
                'reservations'  => [['rateId' => '1', 'passengers' => $passengers]],
            ],
        ]);

        $response = $repo->bookingAmendment($request);
        $data     = $response->getData(true);

        $this->assertArrayHasKey(
            'successfulAmendment',
            $data,
            'Mixed pax net-payment amendment should succeed. Response: ' . json_encode($data)
        );
        $this->assertEquals($fixture['otaCode'], $data['successfulAmendment']['amendmentConfirmationCode']);

        // 2 Childs → 3 Adults: net tourists = 3
        $updatedBooking = DB::table('tour_bookings_master')->where('id', $fixture['bookingId'])->first();
        $this->assertEquals(3, $updatedBooking->tourists, 'Booking tourist count should be updated to 3 after mixed net-payment amendment');
    }

    /**
     * Add an infant to an existing adults-only booking.
     * Infant does not consume seat capacity — slot summary must stay unchanged.
     */
    public function testAmendmentAddInfants(): void
    {
        $fixture = $this->createAmendmentFixture(existingPax: 2, withInfantRate: true);
        $repo    = app(BokunRepository::class);
        $dateObj = Carbon::parse($fixture['tourDate']);
        $timeObj = Carbon::parse($fixture['timeFrom']);

        $request = new Request([
            'bookingConfirmationCode' => $fixture['otaCode'],
            'reservationData' => [
                'productId'     => $fixture['productCode'],
                'date'          => ['year' => $dateObj->year, 'month' => $dateObj->month, 'day' => $dateObj->day],
                'time'          => ['hour' => $timeObj->hour, 'minute' => $timeObj->minute],
                'bookingSource' => ['bookingChannel' => ['title' => 'Headout']],
                'reservations'  => [[
                    'rateId'     => '1',
                    'passengers' => [
                        ['pricingCategoryId' => (string)$fixture['rateId'],       'pricePerPassenger' => ['amount' => 65.00]],
                        ['pricingCategoryId' => (string)$fixture['rateId'],       'pricePerPassenger' => ['amount' => 65.00]],
                        ['pricingCategoryId' => (string)$fixture['infantRateId'], 'pricePerPassenger' => ['amount' => 0.00]],
                    ],
                ]],
            ],
        ]);

        $slotSummaryBefore = DB::table('tour_slot_summaries')->where('tour_slot_id', $fixture['slotTimeId'])->value('booked_tourists') ?? 0;

        $response = $repo->bookingAmendment($request);
        $data     = $response->getData(true);

        $this->assertArrayHasKey('successfulAmendment', $data, 'Adding infant should succeed. Response: ' . json_encode($data));

        $updatedBooking = DB::table('tour_bookings_master')->where('id', $fixture['bookingId'])->first();
        $this->assertEquals(2, $updatedBooking->tourists, 'Tourist count should remain 2');
        $this->assertEquals(1, $updatedBooking->infants,  'Infant count should be updated to 1');

        $slotSummaryAfter = DB::table('tour_slot_summaries')->where('tour_slot_id', $fixture['slotTimeId'])->value('booked_tourists') ?? 0;
        $this->assertEquals($slotSummaryBefore, $slotSummaryAfter, 'Slot summary must not change when only infants are added');
    }

    /**
     * Remove an infant from a booking that has 2 adults + 1 infant.
     * Tourist count must stay at 2; infant count must drop to 0.
     * Slot summary must stay unchanged (infants never counted there).
     */
    public function testAmendmentDeleteInfants(): void
    {
        $fixture = $this->createAmendmentFixture(existingPax: 2, withInfantRate: true, existingInfants: 1);
        $repo    = app(BokunRepository::class);
        $dateObj = Carbon::parse($fixture['tourDate']);
        $timeObj = Carbon::parse($fixture['timeFrom']);

        // Send only the 2 adults — infant omitted → infant is deleted
        $request = new Request([
            'bookingConfirmationCode' => $fixture['otaCode'],
            'reservationData' => [
                'productId'     => $fixture['productCode'],
                'date'          => ['year' => $dateObj->year, 'month' => $dateObj->month, 'day' => $dateObj->day],
                'time'          => ['hour' => $timeObj->hour, 'minute' => $timeObj->minute],
                'bookingSource' => ['bookingChannel' => ['title' => 'Headout']],
                'reservations'  => [[
                    'rateId'     => '1',
                    'passengers' => [
                        ['pricingCategoryId' => (string)$fixture['rateId'], 'pricePerPassenger' => ['amount' => 65.00]],
                        ['pricingCategoryId' => (string)$fixture['rateId'], 'pricePerPassenger' => ['amount' => 65.00]],
                    ],
                ]],
            ],
        ]);

        $slotSummaryBefore = DB::table('tour_slot_summaries')->where('tour_slot_id', $fixture['slotTimeId'])->value('booked_tourists') ?? 0;

        $response = $repo->bookingAmendment($request);
        $data     = $response->getData(true);

        $this->assertArrayHasKey('successfulAmendment', $data, 'Deleting infant should succeed. Response: ' . json_encode($data));

        $updatedBooking = DB::table('tour_bookings_master')->where('id', $fixture['bookingId'])->first();
        $this->assertEquals(2, $updatedBooking->tourists, 'Tourist count should remain 2');
        $this->assertEquals(0, $updatedBooking->infants,  'Infant count should be reduced to 0');

        $slotSummaryAfter = DB::table('tour_slot_summaries')->where('tour_slot_id', $fixture['slotTimeId'])->value('booked_tourists') ?? 0;
        $this->assertEquals($slotSummaryBefore, $slotSummaryAfter, 'Slot summary must not change when only infants are removed');
    }

    /**
     * Reschedule to a new slot AND change pax count in the same amendment.
     * Both $isSlotChanged and $hasPaxChange are true simultaneously.
     */
    public function testAmendmentRescheduleWithPaxChange(): void
    {
        $fixture = $this->createAmendmentFixture(existingPax: 2);
        $repo    = app(BokunRepository::class);
        $now     = Carbon::now();
        $newTime = '16:00:00';

        $newSlotDateId = DB::table('tour_slot_dates')->insertGetId([
            'tour_package_id'      => $fixture['packageId'],
            'date'                 => $fixture['tourDate'],
            'seats'                => 20,
            'status'               => 'Active',
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);
        $this->cleanupSlotDateIds[] = $newSlotDateId;

        $newSlotTimeId = DB::table('tour_slot_times')->insertGetId([
            'tour_slot_id'         => $newSlotDateId,
            'tour_package_id'      => $fixture['packageId'],
            'date'                 => $fixture['tourDate'],
            'time_from'            => $newTime,
            'time_to'              => '17:00:00',
            'time_slot_seats'      => 20,
            'bookable_status'      => 'Open',
            'hide_slot'            => 0,
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);
        $this->cleanupSlotTimeIds[] = $newSlotTimeId;

        $newAffSlotConfigId = DB::table('tour_slot_times_affiliate')->insertGetId([
            'tour_package_id'      => $fixture['packageId'],
            'time_from'            => $newTime,
            'time_to'              => '17:00:00',
            'status'               => 'Active',
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
        ]);
        $this->cleanupAffSlotConfigIds[] = $newAffSlotConfigId;

        $newAffSlotTimeId = DB::table('affiliate_slot_times')->insertGetId([
            'tour_slot_time_id'    => $newAffSlotConfigId,
            'affiliate_id'         => $fixture['customerId'],
            'seat_limit'           => 20,
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
        ]);
        $this->cleanupAffSlotTimeIds[] = $newAffSlotTimeId;

        $packageObj = (object)[
            'package_id'         => $fixture['packageId'],
            'bokun_product_code' => $fixture['productCode'],
            'affiliate_id'       => $fixture['customerId'],
        ];
        $rateGroups = BokunService::rateGroups($packageObj, 'availability');
        $rateId     = (string) $rateGroups->first()->id;

        // Reschedule to new slot AND increase from 2 to 3 tourists
        $passengers = array_fill(0, 3, ['pricingCategoryId' => $rateId, 'pricePerPassenger' => ['amount' => 65.00]]);
        $dateObj    = Carbon::parse($fixture['tourDate']);
        $timeObj    = Carbon::parse($newTime);

        $request = new Request([
            'bookingConfirmationCode' => $fixture['otaCode'],
            'reservationData' => [
                'productId'     => $fixture['productCode'],
                'date'          => ['year' => $dateObj->year, 'month' => $dateObj->month, 'day' => $dateObj->day],
                'time'          => ['hour' => $timeObj->hour, 'minute' => $timeObj->minute],
                'bookingSource' => ['bookingChannel' => ['title' => 'Headout']],
                'reservations'  => [['rateId' => '1', 'passengers' => $passengers]],
            ],
        ]);

        $response = $repo->bookingAmendment($request);
        $data     = $response->getData(true);

        $this->assertArrayHasKey(
            'successfulAmendment',
            $data,
            'Reschedule + pax change together should succeed. Response: ' . json_encode($data)
        );

        $updatedBooking = DB::table('tour_bookings_master')->where('id', $fixture['bookingId'])->first();
        $this->assertEquals($newSlotTimeId, $updatedBooking->tour_slot_id, 'Booking should be on the new slot');
        $this->assertEquals(3, $updatedBooking->tourists, 'Tourist count should be updated to 3');
    }

    // -------------------------------------------------------------------------
    // OTA cutoff tests
    // -------------------------------------------------------------------------

    /**
     * Reschedule to a new slot that is within the OTA cutoff window must be blocked.
     *
     * Package: ota_duration=168h (7 days), package_has_slots=1.
     * New slot: 6 days from now (within the 7-day cutoff).
     * Expected: failedAmendment with cutoff error.
     */
    public function testAmendmentRescheduleBlockedByOtaCutoff(): void
    {
        $fixture = $this->createAmendmentFixture(existingPax: 2, otaDuration: 168, packageHasSlots: 1);
        $repo    = app(BokunRepository::class);
        $now     = Carbon::now();
        $newTime = '13:00:00';

        // New slot on the same date — also 6 days from now, within the 168-hour cutoff
        $newSlotDateId = DB::table('tour_slot_dates')->insertGetId([
            'tour_package_id'      => $fixture['packageId'],
            'date'                 => $fixture['tourDate'],
            'seats'                => 20,
            'status'               => 'Active',
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);
        $this->cleanupSlotDateIds[] = $newSlotDateId;

        $newSlotTimeId = DB::table('tour_slot_times')->insertGetId([
            'tour_slot_id'         => $newSlotDateId,
            'tour_package_id'      => $fixture['packageId'],
            'date'                 => $fixture['tourDate'],
            'time_from'            => $newTime,
            'time_to'              => '14:00:00',
            'time_slot_seats'      => 20,
            'bookable_status'      => 'Open',
            'hide_slot'            => 0,
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);
        $this->cleanupSlotTimeIds[] = $newSlotTimeId;

        $newAffSlotConfigId = DB::table('tour_slot_times_affiliate')->insertGetId([
            'tour_package_id'      => $fixture['packageId'],
            'time_from'            => $newTime,
            'time_to'              => '14:00:00',
            'status'               => 'Active',
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
        ]);
        $this->cleanupAffSlotConfigIds[] = $newAffSlotConfigId;

        $newAffSlotTimeId = DB::table('affiliate_slot_times')->insertGetId([
            'tour_slot_time_id'    => $newAffSlotConfigId,
            'affiliate_id'         => $fixture['customerId'],
            'seat_limit'           => 20,
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
        ]);
        $this->cleanupAffSlotTimeIds[] = $newAffSlotTimeId;

        $packageObj = (object)[
            'package_id'         => $fixture['packageId'],
            'bokun_product_code' => $fixture['productCode'],
            'affiliate_id'       => $fixture['customerId'],
        ];
        $rateGroups = BokunService::rateGroups($packageObj, 'availability');
        $rateId     = (string) $rateGroups->first()->id;

        $passengers = array_fill(0, 2, ['pricingCategoryId' => $rateId, 'pricePerPassenger' => ['amount' => 65.00]]);
        $dateObj    = Carbon::parse($fixture['tourDate']);
        $timeObj    = Carbon::parse($newTime);

        $request = new Request([
            'bookingConfirmationCode' => $fixture['otaCode'],
            'reservationData' => [
                'productId'     => $fixture['productCode'],
                'date'          => ['year' => $dateObj->year, 'month' => $dateObj->month, 'day' => $dateObj->day],
                'time'          => ['hour' => $timeObj->hour, 'minute' => $timeObj->minute],
                'bookingSource' => ['bookingChannel' => ['title' => 'Headout']],
                'reservations'  => [['rateId' => '1', 'passengers' => $passengers]],
            ],
        ]);

        $response = $repo->bookingAmendment($request);
        $data     = $response->getData(true);

        $this->assertArrayHasKey('failedAmendment', $data, 'Reschedule to slot within OTA cutoff should be rejected. Response: ' . json_encode($data));
        $this->assertStringContainsString('cutoff', $data['failedAmendment']['amendmentError']);
    }

    /**
     * Pax-only change on a slot within the OTA cutoff window must NOT be blocked.
     *
     * Package: ota_duration=168h (7 days), package_has_slots=1.
     * Slot: 6 days from now (within the 7-day cutoff).
     * Amendment: same slot, different pax count → $isSlotChanged=false → cutoff skipped.
     * Expected: successfulAmendment.
     */
    public function testAmendmentPaxOnlyChangeNotBlockedByOtaCutoff(): void
    {
        $fixture = $this->createAmendmentFixture(existingPax: 2, otaDuration: 168, packageHasSlots: 1);
        $repo    = app(BokunRepository::class);

        // Same slot, different pax count (2 → 3) — slot is not changing
        $request = new Request($this->buildAmendmentRequest($fixture, paxCount: 3));

        $response = $repo->bookingAmendment($request);
        $data     = $response->getData(true);

        $this->assertArrayHasKey(
            'successfulAmendment',
            $data,
            'Pax-only change on a slot within the OTA cutoff should succeed — cutoff only applies to reschedules. Response: ' . json_encode($data)
        );
        $this->assertEquals($fixture['otaCode'], $data['successfulAmendment']['amendmentConfirmationCode']);

        $updatedBooking = DB::table('tour_bookings_master')->where('id', $fixture['bookingId'])->first();
        $this->assertEquals(3, $updatedBooking->tourists, 'Tourist count should be updated to 3');
    }

    /**
     * Reschedule to a slot outside the OTA cutoff window must succeed.
     *
     * Package: ota_duration=2h, package_has_slots=1.
     * New slot: 6 days from now (well outside the 2-hour cutoff).
     * Expected: successfulAmendment.
     */
    public function testAmendmentRescheduleSucceedsOutsideOtaCutoff(): void
    {
        $fixture = $this->createAmendmentFixture(existingPax: 2, otaDuration: 2, packageHasSlots: 1);
        $repo    = app(BokunRepository::class);
        $now     = Carbon::now();
        $newTime = '17:00:00';

        $newSlotDateId = DB::table('tour_slot_dates')->insertGetId([
            'tour_package_id'      => $fixture['packageId'],
            'date'                 => $fixture['tourDate'],
            'seats'                => 20,
            'status'               => 'Active',
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);
        $this->cleanupSlotDateIds[] = $newSlotDateId;

        $newSlotTimeId = DB::table('tour_slot_times')->insertGetId([
            'tour_slot_id'         => $newSlotDateId,
            'tour_package_id'      => $fixture['packageId'],
            'date'                 => $fixture['tourDate'],
            'time_from'            => $newTime,
            'time_to'              => '18:00:00',
            'time_slot_seats'      => 20,
            'bookable_status'      => 'Open',
            'hide_slot'            => 0,
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);
        $this->cleanupSlotTimeIds[] = $newSlotTimeId;

        $newAffSlotConfigId = DB::table('tour_slot_times_affiliate')->insertGetId([
            'tour_package_id'      => $fixture['packageId'],
            'time_from'            => $newTime,
            'time_to'              => '18:00:00',
            'status'               => 'Active',
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
        ]);
        $this->cleanupAffSlotConfigIds[] = $newAffSlotConfigId;

        $newAffSlotTimeId = DB::table('affiliate_slot_times')->insertGetId([
            'tour_slot_time_id'    => $newAffSlotConfigId,
            'affiliate_id'         => $fixture['customerId'],
            'seat_limit'           => 20,
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => $now,
        ]);
        $this->cleanupAffSlotTimeIds[] = $newAffSlotTimeId;

        $packageObj = (object)[
            'package_id'         => $fixture['packageId'],
            'bokun_product_code' => $fixture['productCode'],
            'affiliate_id'       => $fixture['customerId'],
        ];
        $rateGroups = BokunService::rateGroups($packageObj, 'availability');
        $rateId     = (string) $rateGroups->first()->id;

        $passengers = array_fill(0, 2, ['pricingCategoryId' => $rateId, 'pricePerPassenger' => ['amount' => 65.00]]);
        $dateObj    = Carbon::parse($fixture['tourDate']);
        $timeObj    = Carbon::parse($newTime);

        $request = new Request([
            'bookingConfirmationCode' => $fixture['otaCode'],
            'reservationData' => [
                'productId'     => $fixture['productCode'],
                'date'          => ['year' => $dateObj->year, 'month' => $dateObj->month, 'day' => $dateObj->day],
                'time'          => ['hour' => $timeObj->hour, 'minute' => $timeObj->minute],
                'bookingSource' => ['bookingChannel' => ['title' => 'Headout']],
                'reservations'  => [['rateId' => '1', 'passengers' => $passengers]],
            ],
        ]);

        $response = $repo->bookingAmendment($request);
        $data     = $response->getData(true);

        $this->assertArrayHasKey(
            'successfulAmendment',
            $data,
            'Reschedule to slot outside OTA cutoff should succeed. Response: ' . json_encode($data)
        );
        $this->assertEquals($fixture['otaCode'], $data['successfulAmendment']['amendmentConfirmationCode']);

        $updatedBooking = DB::table('tour_bookings_master')->where('id', $fixture['bookingId'])->first();
        $this->assertEquals($newSlotTimeId, $updatedBooking->tour_slot_id, 'Booking should be rescheduled to the new slot');
    }

    // -------------------------------------------------------------------------
    // Cleanup — runs after each test while the app (and DB connection) is live
    // -------------------------------------------------------------------------

    protected function tearDown(): void
    {
        // bookingAmendment may leave open transactions on early-return failure paths.
        // Roll back any open transaction so our deletes are not rolled back when the
        // connection is dropped by parent::tearDown().
        if (DB::transactionLevel() > 0) {
            try { DB::rollBack(); } catch (\Throwable $e) {}
        }

        $del = function (string $table, string $col, $val): void {
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
                DB::table($table)->where($col, $val)->delete();
            } catch (\Throwable $e) {
                fwrite(STDERR, "[BOKUN-AMEND CLEANUP] $table.$col=$val : " . $e->getMessage() . "\n");
            }
        };

        foreach ($this->cleanupBookingIds as $id) {
            $del('tour_booking_history',   'tour_booking_id', $id);
            $del('transactions',           'tour_booking_id', $id);
            $del('tour_bookings_transact', 'tour_booking_id', $id);
            try {
                $slotId = DB::table('tour_bookings_master')->where('id', $id)->value('tour_slot_id');
                if ($slotId) {
                    $del('tour_slot_summaries', 'tour_slot_id', $slotId);
                }
            } catch (\Throwable $e) {}
            $del('tour_bookings_master', 'id', $id);
        }

        // Orphaned transact/transaction rows from pax-change tests
        foreach ($this->cleanupTransactIds     as $id) { $del('tour_bookings_transact', 'id', $id); }
        foreach ($this->cleanupTransactionIds  as $id) { $del('transactions',           'id', $id); }

        foreach ($this->cleanupAffSlotTimeIds   as $id) { $del('affiliate_slot_times',      'id', $id); }
        foreach ($this->cleanupAffSlotConfigIds as $id) { $del('tour_slot_times_affiliate', 'id', $id); }
        foreach ($this->cleanupSlotTimeIds      as $id) {
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
