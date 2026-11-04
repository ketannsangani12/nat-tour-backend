<?php

namespace Tests\Unit\Octo;

use App\Models\TourBooking;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers OctoLegacyBookingBackfillService — lazily resolving/backfilling an octo_bookings
 * row for a booking made through the old Bokun channel-manager integration before OCTO
 * existed for that OTA, so getBooking/updateBooking/cancelBooking work on it exactly as
 * they would for a native OCTO booking.
 *
 * Run with:
 *   docker exec -it nat-app php artisan test tests/Unit/Octo/OctoLegacyBookingBackfillTest.php
 *
 * Same safety notes as OctoRepositoryTest — no DatabaseTransactions (OctoAuthentication
 * calls DB::purge()/reconnect() per request), manual cleanup instead, all fixtures live in
 * tenant_dixies on the local dev DB only (see .env: DB_HOST=127.0.0.1, APP_ENV=local).
 *
 * RESELLER_NAME is deliberately 'headout' (not a generic test string) so that
 * BokunUtils::initPrefix() resolves the real 'HEAD' prefix from config/constants.php —
 * the whole point of these tests is exercising the real Bokun-vs-OCTO rate-sheet split
 * that only exists for OTAs with both channels (currently just Headout).
 */
class OctoLegacyBookingBackfillTest extends TestCase
{
    private const TEST_KEY      = 'octo-test-key-legacy-backfill';
    // Deliberately NOT the bare 'headout' — that (tenant_id, reseller) pair already exists
    // as a real octo_keys row for tenant dixies, and octo_keys has a unique constraint on
    // that pair, so insertOrIgnore() would silently skip our row and leave the real key's
    // hash in place, causing every request here to 403 as "invalid API key". This string
    // still resolves the real 'HEAD' prefix via BokunUtils::initPrefix()'s substring-alias
    // match (it contains "headout"), which is the actual thing under test.
    private const RESELLER_NAME = 'headout-legacy-backfill-test';
    private const TEST_TENANT   = 'dixies';

    protected array $cleanupCustomerIds      = [];
    protected array $cleanupPackageIds       = [];
    protected array $cleanupRateIds          = [];
    protected array $cleanupSlotIds          = [];
    protected array $cleanupSlotDateIds      = [];
    protected array $cleanupTstaIds          = [];
    protected array $cleanupTourBookingIds   = [];
    protected array $cleanupOctoBookingUuids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $base = config('database.connections.mysql');
        Config::set('database.connections.tenant', array_merge($base, [
            'database' => 'tenant_' . self::TEST_TENANT,
        ]));
        DB::purge('tenant');

        DB::connection('mysql')->table('octo_keys')->insertOrIgnore([
            'tenant_id'    => self::TEST_TENANT,
            'reseller'     => self::RESELLER_NAME,
            'api_key_hash' => hash('sha256', self::TEST_KEY),
            'key_prefix'   => 'octo-test-',
            'is_active'    => 1,
            'created_at'   => Carbon::now(),
            'updated_at'   => Carbon::now(),
        ]);
    }

    protected function tearDown(): void
    {
        if (!empty($this->cleanupOctoBookingUuids)) {
            DB::connection('tenant')->table('octo_bookings')->whereIn('uuid', $this->cleanupOctoBookingUuids)->delete();
        }

        if (!empty($this->cleanupTourBookingIds)) {
            DB::connection('tenant')->table('tour_booking_history')->whereIn('tour_booking_id', $this->cleanupTourBookingIds)->delete();
            DB::connection('tenant')->table('transactions')->whereIn('tour_booking_id', $this->cleanupTourBookingIds)->delete();
            DB::connection('tenant')->table('tour_bookings_transact')->whereIn('tour_booking_id', $this->cleanupTourBookingIds)->delete();
            DB::connection('tenant')->table('audits')
                ->where('auditable_type', TourBooking::class)
                ->whereIn('auditable_id', $this->cleanupTourBookingIds)
                ->delete();
            DB::connection('tenant')->table('tour_bookings_master')->whereIn('id', $this->cleanupTourBookingIds)->delete();
        }

        if (!empty($this->cleanupSlotIds)) {
            DB::connection('tenant')->table('tour_slot_summaries')->whereIn('tour_slot_id', $this->cleanupSlotIds)->delete();
            DB::connection('tenant')->table('affiliate_slot_times')->whereIn('tour_slot_time_id', $this->cleanupTstaIds)->delete();
            DB::connection('tenant')->table('tour_slot_times_affiliate')->whereIn('id', $this->cleanupTstaIds)->delete();
            DB::connection('tenant')->table('tour_slot_times')->whereIn('id', $this->cleanupSlotIds)->delete();
        }
        if (!empty($this->cleanupSlotDateIds)) {
            DB::connection('tenant')->table('tour_slot_dates')->whereIn('id', $this->cleanupSlotDateIds)->delete();
        }

        if (!empty($this->cleanupRateIds)) {
            DB::connection('tenant')->table('tour_package_rates')->whereIn('id', $this->cleanupRateIds)->delete();
        }
        if (!empty($this->cleanupPackageIds)) {
            DB::connection('tenant')->table('package_affiliate_assignments')->whereIn('package_id', $this->cleanupPackageIds)->delete();
            DB::connection('tenant')->table('tour_packages')->whereIn('id', $this->cleanupPackageIds)->delete();
        }
        if (!empty($this->cleanupCustomerIds)) {
            DB::connection('tenant')->table('customers')->whereIn('id', $this->cleanupCustomerIds)->delete();
        }

        DB::connection('mysql')->table('octo_keys')->where('api_key_hash', hash('sha256', self::TEST_KEY))->delete();

        parent::tearDown();
    }

    private function octoHeaders(string $capabilities = ''): array
    {
        return [
            'Authorization'     => 'Bearer ' . self::TEST_KEY,
            'Octo-Capabilities' => $capabilities,
            'Accept'            => 'application/json',
        ];
    }

    // ── Fixtures ─────────────────────────────────────────────────────────────
    // Mirrors the real production shape found on tenant `dixies`: a generic "Bokun"
    // customer (bokun=1, is_ota=0) that legacy bookings' customer_id points at, and a
    // separate "Headout" OCTO customer (is_ota=1, ota_id='headout') that authenticates
    // OCTO calls — with two distinct tour_package_rates rows per rate tier, one under
    // each affiliate, linked only by a shared mapping_key.

    private function createBokunAffiliate(): int
    {
        $operatorId = DB::connection('tenant')->table('tour_operators')->value('id');
        $id = DB::connection('tenant')->table('customers')->insertGetId([
            'tour_operator_id' => $operatorId,
            'name'             => 'Bokun',
            'email'            => 'bokun-' . uniqid() . '@example.com',
            'phone_number'     => '0000000000',
            'is_affiliate'     => 'Yes',
            'is_ota'           => 0,
            'bokun'            => 1,
            'status'           => 'Active',
            'created_at'       => Carbon::now(),
            'updated_at'       => Carbon::now(),
        ]);
        $this->cleanupCustomerIds[] = $id;
        return $id;
    }

    private function createOctoAffiliate(): int
    {
        $operatorId = DB::connection('tenant')->table('tour_operators')->value('id');
        $id = DB::connection('tenant')->table('customers')->insertGetId([
            'tour_operator_id'         => $operatorId,
            'name'                     => 'Headout',
            'email'                    => 'headout-' . uniqid() . '@example.com',
            'phone_number'             => '0000000000',
            'is_affiliate'             => 'Yes',
            'is_ota'                   => 1,
            'ota_id'                   => self::RESELLER_NAME,
            'ota_affiliate_percentage' => 10,
            'status'                   => 'Active',
            'created_at'               => Carbon::now(),
            'updated_at'               => Carbon::now(),
        ]);
        $this->cleanupCustomerIds[] = $id;
        return $id;
    }

    private function createPackage(int $octoAffiliateId): int
    {
        $operatorId = DB::connection('tenant')->table('tour_operators')->value('id');
        $packageId = DB::connection('tenant')->table('tour_packages')->insertGetId([
            'tour_operator_id'              => $operatorId,
            'name'                          => 'Legacy Backfill Test Package ' . uniqid(),
            'status'                        => 'Active',
            'type'                          => 'Regular',
            'service_commission_percentage' => 0,
            'stripe_commission_percentage'  => 0,
            'created_by'                    => 1,
            'created_by_user_type'          => 'Admin',
            'created_at'                    => Carbon::now(),
            'updated_at'                    => Carbon::now(),
        ]);
        $this->cleanupPackageIds[] = $packageId;

        DB::connection('tenant')->table('package_affiliate_assignments')->insert([
            'affiliate_id' => $octoAffiliateId,
            'package_id'   => $packageId,
            'created_at'   => Carbon::now(),
            'updated_at'   => Carbon::now(),
        ]);

        return $packageId;
    }

    private function createRate(int $packageId, int $affiliateId, int $mappingKey, array $overrides = []): int
    {
        $id = DB::connection('tenant')->table('tour_package_rates')->insertGetId(array_merge([
            'tour_package_id'              => $packageId,
            'affiliate_id'                 => $affiliateId,
            'rate_for'                     => 'Adult',
            'age_from'                     => 18,
            'age_to'                       => 99,
            'rate'                         => 55.00,
            'permit_fee'                   => 15.00,
            'additional_charge'            => 3.30,
            'processing_charge_percentage' => '0',
            'processing_charge'            => 0,
            'additional_tax_percentage'    => 0,
            'margin'                       => 0,
            'scheduling_year'              => (int) date('Y'),
            'status'                       => 'Active',
            'orderby'                      => 1,
            'mapping_key'                  => $mappingKey,
            'is_infant_rate'               => 0,
            'is_group_rate'                => 0,
            'description'                  => '',
            'created_by'                   => 1,
            'created_by_user_type'         => 'Admin',
            'created_at'                   => Carbon::now(),
            'updated_at'                   => Carbon::now(),
            'deleted_by'                   => 0,
        ], $overrides));

        $this->cleanupRateIds[] = $id;
        return $id;
    }

    private function createBookableSlot(int $packageId, int $affiliateId, int $seatLimit = 10, array $overrides = []): object
    {
        $timeFrom = $overrides['time_from'] ?? '10:00:00';
        $timeTo   = $overrides['time_to'] ?? '12:00:00';
        $date     = $overrides['date'] ?? Carbon::now()->addDays(45)->format('Y-m-d');

        // tour_slot_times.tour_slot_id is a FK to tour_slot_dates, so the parent
        // date row has to exist before the time row can be inserted.
        $slotDateId = DB::connection('tenant')->table('tour_slot_dates')->insertGetId([
            'tour_package_id' => $packageId,
            'date'            => $date,
            'seats'           => 100,
            'status'          => 'Active',
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now(),
        ]);
        $this->cleanupSlotDateIds[] = $slotDateId;

        $slotId = DB::connection('tenant')->table('tour_slot_times')->insertGetId([
            'tour_package_id' => $packageId,
            'tour_slot_id'    => $slotDateId,
            'date'            => $date,
            'time_from'       => $timeFrom,
            'time_to'         => $timeTo,
            'time_slot_seats' => 100,
            'bookable_status' => 'Open',
            'hide_slot'       => 0,
            'deleted_by'      => 0,
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now(),
        ]);
        $this->cleanupSlotIds[] = $slotId;

        $tstaId = DB::connection('tenant')->table('tour_slot_times_affiliate')->insertGetId([
            'tour_package_id' => $packageId,
            'time_from'       => $timeFrom,
            'time_to'         => $timeTo,
            'status'          => 'Active',
            'deleted_by'      => 0,
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now(),
        ]);
        $this->cleanupTstaIds[] = $tstaId;

        DB::connection('tenant')->table('affiliate_slot_times')->insert([
            'tour_slot_time_id' => $tstaId,
            'affiliate_id'      => $affiliateId,
            'seat_limit'        => $seatLimit,
            'deleted_by'        => 0,
            'created_at'        => Carbon::now(),
            'updated_at'        => Carbon::now(),
        ]);

        return (object) ['id' => $slotId, 'date' => $date, 'time_from' => $timeFrom, 'time_to' => $timeTo];
    }

    /**
     * Directly inserts a tour_bookings_master + tour_bookings_transact + transactions row
     * exactly as the old Bokun integration would have — no octo_bookings row, customer_id
     * pointing at the generic Bokun customer, rate id from the Bokun affiliate's own rate
     * sheet (never the OCTO affiliate's).
     */
    private function createLegacyBokunBooking(int $packageId, object $slot, int $bokunAffiliateId, int $bokunRateId, string $otaPrefix, string $resellerReference, array $overrides = []): int
    {
        $tourists = $overrides['tourists'] ?? 2;
        $rate     = $overrides['rate'] ?? 55.00;
        $total    = round(($rate + 3.30 + 15.00) * $tourists, 2);
        // 'rate' is a pseudo-key for this helper (drives the transact row below), not a
        // real tour_bookings_master column — strip it before merging into that insert.
        $masterOverrides = collect($overrides)->except(['rate'])->all();

        $bookingId = DB::connection('tenant')->table('tour_bookings_master')->insertGetId(array_merge([
            'ota_reference_booking'    => $otaPrefix . '-' . $resellerReference,
            'tour_package_id'          => $packageId,
            'tour_slot_id'             => $slot->id,
            'customer_id'              => $bokunAffiliateId,
            'name'                     => 'Jane Doe',
            'email'                    => 'jane@example.com',
            'phone_number'             => '555-0100',
            'tourists'                 => $tourists,
            'infants'                  => 0,
            'comments'                 => '',
            'note'                     => '',
            'mask_required'            => 'Yes',
            'total'                    => $total,
            'booking_total'            => $total,
            'liability_waiver_signing' => '',
            'status'                   => 'Booked',
            'request_from'             => 'Headout',
            'created_by'               => $bokunAffiliateId,
            'created_by_user_type'     => 'Affiliate',
            'created_at'               => Carbon::now()->subDays(10),
            'updated_at'               => Carbon::now()->subDays(10),
            'deleted_by'               => 0,
        ], $masterOverrides));

        $this->cleanupTourBookingIds[] = $bookingId;

        DB::connection('tenant')->table('tour_bookings_transact')->insert([
            'tour_booking_id'              => $bookingId,
            'tour_package_rate_id'         => $bokunRateId,
            'tourists'                     => $tourists,
            'processing_charge'            => 0,
            'additional_tax_percentage'    => 0,
            'processing_charge_percentage' => 0,
            'nat_fees_percentage'          => 0,
            'stripe_fees_percentage'       => 0,
            'permit_fee'                   => 15.00,
            'additional_charge'            => 3.30,
            'state_tax'                    => 0,
            'rate'                         => $rate,
            'total'                        => $total,
            'check_in_status'              => '',
            'is_infant_rate'               => 0,
            'is_group_rate'                => 0,
            'created_by'                   => $bokunAffiliateId,
            'created_by_user_type'         => 'Affiliate',
            'created_at'                   => Carbon::now()->subDays(10),
            'updated_at'                   => Carbon::now()->subDays(10),
            'deleted_by'                   => 0,
        ]);

        DB::connection('tenant')->table('transactions')->insert([
            'tour_booking_id'      => $bookingId,
            'amount'               => $total,
            'payment_type'         => 0,
            'response_init'        => '',
            'response_end'         => '',
            'via'                  => 'Headout',
            'status'               => 'Completed',
            'created_by_user_type' => 'Affiliate',
            'created_at'           => Carbon::now()->subDays(10),
            'deleted_by'           => 0,
        ]);

        return $bookingId;
    }

    private function trackOctoBookingFor(int $tourBookingId): ?string
    {
        $uuid = DB::connection('tenant')->table('octo_bookings')->where('tour_booking_id', $tourBookingId)->value('uuid');
        if ($uuid) {
            $this->cleanupOctoBookingUuids[] = $uuid;
        }
        return $uuid;
    }

    // ── Tests ────────────────────────────────────────────────────────────────

    public function test_cancel_backfills_octo_booking_and_issues_refund(): void
    {
        $bokunAffiliateId = $this->createBokunAffiliate();
        $octoAffiliateId  = $this->createOctoAffiliate();
        $packageId        = $this->createPackage($octoAffiliateId);
        $bokunRateId      = $this->createRate($packageId, $bokunAffiliateId, 1);
        $octoRateId       = $this->createRate($packageId, $octoAffiliateId, 1);
        $slot             = $this->createBookableSlot($packageId, $octoAffiliateId, 10);

        $tourBookingId = $this->createLegacyBokunBooking($packageId, $slot, $bokunAffiliateId, $bokunRateId, 'HEAD', '100001');

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings/100001/cancel', [
            'reason' => 'Guest requested cancellation',
        ]);
        $this->trackOctoBookingFor($tourBookingId);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'CANCELLED');

        $octoBooking = DB::connection('tenant')->table('octo_bookings')->where('tour_booking_id', $tourBookingId)->first();
        $this->assertNotNull($octoBooking, 'Cancelling a legacy booking must backfill an octo_bookings row.');
        $this->assertEquals($octoAffiliateId, $octoBooking->affiliate_id, 'Backfilled row must belong to the OCTO affiliate, not the generic Bokun customer.');

        $unitItems = json_decode($octoBooking->unit_items, true);
        $this->assertSame((string) $octoRateId, $unitItems[0]['unitId'], 'unit_items must hold the OCTO-affiliate rate id, translated via mapping_key.');

        $this->assertSame('Cancelled', DB::connection('tenant')->table('tour_bookings_master')->where('id', $tourBookingId)->value('status'));

        $refund = DB::connection('tenant')->table('transactions')->where('tour_booking_id', $tourBookingId)->where('status', 'Refund')->first();
        $this->assertNotNull($refund, 'Cancelling a legacy CONFIRMED booking must still issue a real refund.');
        $this->assertEquals(146.60, (float) $refund->amount);

        // The historical transact row is re-pointed at the OCTO-affiliate rate id, so a
        // later OCTO update diffs old-vs-new rate composition correctly (see next test).
        $transactRateId = DB::connection('tenant')->table('tour_bookings_transact')->where('tour_booking_id', $tourBookingId)->value('tour_package_rate_id');
        $this->assertEquals($octoRateId, $transactRateId, 'Legacy transact row must be re-pointed to the OCTO-affiliate rate id at backfill time.');
    }

    public function test_get_booking_resolves_legacy_booking_by_full_ota_reference(): void
    {
        $bokunAffiliateId = $this->createBokunAffiliate();
        $octoAffiliateId  = $this->createOctoAffiliate();
        $packageId        = $this->createPackage($octoAffiliateId);
        $bokunRateId      = $this->createRate($packageId, $bokunAffiliateId, 1);
        $this->createRate($packageId, $octoAffiliateId, 1);
        $slot             = $this->createBookableSlot($packageId, $octoAffiliateId, 10);

        $tourBookingId = $this->createLegacyBokunBooking($packageId, $slot, $bokunAffiliateId, $bokunRateId, 'HEAD', '100002');

        // Full "HEAD-100002" string in the {uuid} slot — the shape a client would send if
        // it just replays the bookingConfirmationCode it already has, without ever calling
        // a separate lookup-by-reference step first.
        $response = $this->withHeaders($this->octoHeaders())->getJson('/api/v1/octo/bookings/HEAD-100002');
        $this->trackOctoBookingFor($tourBookingId);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'CONFIRMED');
        $response->assertJsonPath('resellerReference', '100002');

        $this->assertSame(1, DB::connection('tenant')->table('octo_bookings')->where('tour_booking_id', $tourBookingId)->count());
    }

    public function test_repeated_lookups_do_not_duplicate_the_backfilled_row(): void
    {
        $bokunAffiliateId = $this->createBokunAffiliate();
        $octoAffiliateId  = $this->createOctoAffiliate();
        $packageId        = $this->createPackage($octoAffiliateId);
        $bokunRateId      = $this->createRate($packageId, $bokunAffiliateId, 1);
        $this->createRate($packageId, $octoAffiliateId, 1);
        $slot             = $this->createBookableSlot($packageId, $octoAffiliateId, 10);

        $tourBookingId = $this->createLegacyBokunBooking($packageId, $slot, $bokunAffiliateId, $bokunRateId, 'HEAD', '100003');

        $first  = $this->withHeaders($this->octoHeaders())->getJson('/api/v1/octo/bookings/100003');
        $second = $this->withHeaders($this->octoHeaders())->getJson('/api/v1/octo/bookings/100003');
        $this->trackOctoBookingFor($tourBookingId);

        $first->assertStatus(200);
        $second->assertStatus(200);
        $this->assertSame($first->json('uuid'), $second->json('uuid'), 'Both lookups must resolve to the same backfilled octo_bookings row.');
        $this->assertSame(1, DB::connection('tenant')->table('octo_bookings')->where('tour_booking_id', $tourBookingId)->count());
    }

    public function test_missing_octo_rate_mapping_returns_400_not_500(): void
    {
        $bokunAffiliateId = $this->createBokunAffiliate();
        $octoAffiliateId  = $this->createOctoAffiliate();
        $packageId        = $this->createPackage($octoAffiliateId);
        // mapping_key=99 on the Bokun side has no counterpart under the OCTO affiliate.
        $bokunRateId      = $this->createRate($packageId, $bokunAffiliateId, 99);
        $slot             = $this->createBookableSlot($packageId, $octoAffiliateId, 10);

        $tourBookingId = $this->createLegacyBokunBooking($packageId, $slot, $bokunAffiliateId, $bokunRateId, 'HEAD', '100004');
        $this->cleanupTourBookingIds[] = $tourBookingId; // ensure cleanup even though backfill never completes

        $response = $this->withHeaders($this->octoHeaders())->getJson('/api/v1/octo/bookings/100004');

        $response->assertStatus(400);
        $response->assertJson(['error' => 'UNPROCESSABLE_ENTITY']);
        $this->assertSame(0, DB::connection('tenant')->table('octo_bookings')->where('tour_booking_id', $tourBookingId)->count());
    }

    public function test_unknown_identifier_still_returns_invalid_booking_uuid(): void
    {
        $this->createOctoAffiliate();

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings/does-not-exist/cancel', []);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'INVALID_BOOKING_UUID']);
    }

    public function test_cannot_resolve_another_otas_booking_via_headout_token(): void
    {
        $bokunAffiliateId = $this->createBokunAffiliate();
        $octoAffiliateId  = $this->createOctoAffiliate();
        $packageId        = $this->createPackage($octoAffiliateId);
        $bokunRateId      = $this->createRate($packageId, $bokunAffiliateId, 1);
        $slot             = $this->createBookableSlot($packageId, $octoAffiliateId, 10);

        // A GetYourGuide booking (different OTA prefix) with the same numeric reference.
        $gygBookingId = $this->createLegacyBokunBooking($packageId, $slot, $bokunAffiliateId, $bokunRateId, 'GYG', '100005');

        // Authenticated as Headout — must not be able to discover the GYG booking by
        // guessing/replaying its full reference string.
        $response = $this->withHeaders($this->octoHeaders())->getJson('/api/v1/octo/bookings/GYG-100005');

        $response->assertStatus(400);
        $response->assertJson(['error' => 'INVALID_BOOKING_UUID']);
        $this->assertSame(0, DB::connection('tenant')->table('octo_bookings')->where('tour_booking_id', $gygBookingId)->count());
    }

    public function test_pricing_reflects_historical_charge_not_a_later_rate_change(): void
    {
        $bokunAffiliateId = $this->createBokunAffiliate();
        $octoAffiliateId  = $this->createOctoAffiliate();
        $packageId        = $this->createPackage($octoAffiliateId);
        $bokunRateId      = $this->createRate($packageId, $bokunAffiliateId, 1, ['rate' => 55.00]);
        $octoRateId       = $this->createRate($packageId, $octoAffiliateId, 1, ['rate' => 55.00]);
        $slot             = $this->createBookableSlot($packageId, $octoAffiliateId, 10);

        $tourBookingId = $this->createLegacyBokunBooking($packageId, $slot, $bokunAffiliateId, $bokunRateId, 'HEAD', '100006', ['rate' => 55.00]);

        // Simulate the operator raising the OCTO affiliate's live rate well after the booking
        // was made — pricing shown for this already-confirmed booking must not follow it.
        DB::connection('tenant')->table('tour_package_rates')->where('id', $octoRateId)->update(['rate' => 999.00]);

        $response = $this->withHeaders($this->octoHeaders('pricing'))->getJson('/api/v1/octo/bookings/100006');
        $this->trackOctoBookingFor($tourBookingId);

        $response->assertStatus(200);
        // pricing.net is the aggregate booking-level net (both adults): 2 x (55.00 rate +
        // 3.30 additional_charge + 15.00 permit_fee) = 146.60 = 14660 minor units. If this
        // were re-computed live against the 999.00 rate it would be ~204,660 instead.
        $response->assertJsonPath('pricing.net', 14660);
    }

    public function test_update_pax_change_does_not_misfire_refund_and_recharge_for_unchanged_tier(): void
    {
        $bokunAffiliateId = $this->createBokunAffiliate();
        $octoAffiliateId  = $this->createOctoAffiliate();
        $packageId        = $this->createPackage($octoAffiliateId);
        $bokunAdultRateId = $this->createRate($packageId, $bokunAffiliateId, 1, ['rate_for' => 'Adult']);
        $octoAdultRateId  = $this->createRate($packageId, $octoAffiliateId, 1, ['rate_for' => 'Adult']);
        $octoChildRateId  = $this->createRate($packageId, $octoAffiliateId, 2, ['rate_for' => 'Child', 'age_from' => 4, 'age_to' => 17, 'rate' => 40.00]);
        $slot             = $this->createBookableSlot($packageId, $octoAffiliateId, 10);

        // 2 adults, booked via Bokun under the Bokun-scoped adult rate id.
        $tourBookingId = $this->createLegacyBokunBooking($packageId, $slot, $bokunAffiliateId, $bokunAdultRateId, 'HEAD', '100007', ['tourists' => 2]);

        // Backfill first via a plain GET, then submit an update that keeps the 2 adults
        // (now expressed under the OCTO adult rate id, as Headout's OCTO client would) and
        // adds 1 child — a real, deliberate addition. Before the FK-migration fix, the old
        // Bokun-scoped adult row and the new OCTO-scoped adult id would look like two
        // unrelated rate groups, triggering a bogus refund of the 2 "removed" adults and a
        // recharge for 2 "new" ones.
        $this->withHeaders($this->octoHeaders())->getJson('/api/v1/octo/bookings/100007');
        $this->trackOctoBookingFor($tourBookingId);

        $octoBooking = DB::connection('tenant')->table('octo_bookings')->where('tour_booking_id', $tourBookingId)->first();

        $response = $this->withHeaders($this->octoHeaders())->patchJson('/api/v1/octo/bookings/' . $octoBooking->uuid, [
            'unitItems' => [
                ['unitId' => (string) $octoAdultRateId],
                ['unitId' => (string) $octoAdultRateId],
                ['unitId' => (string) $octoChildRateId],
            ],
        ]);

        $response->assertStatus(200);

        $refundCount = DB::connection('tenant')->table('transactions')->where('tour_booking_id', $tourBookingId)->where('status', 'Refund')->count();
        $this->assertSame(0, $refundCount, 'Keeping the same 2 adults must not trigger a refund — the old and new adult rate ids represent the same tier.');

        $adultRow = DB::connection('tenant')->table('tour_bookings_transact')
            ->where('tour_booking_id', $tourBookingId)->where('tour_package_rate_id', $octoAdultRateId)->first();
        $this->assertNotNull($adultRow, 'The adult row must have been re-pointed to the OCTO rate id, not left under the Bokun one.');
        $this->assertEquals(2, $adultRow->tourists);

        $childRow = DB::connection('tenant')->table('tour_bookings_transact')
            ->where('tour_booking_id', $tourBookingId)->where('tour_package_rate_id', $octoChildRateId)->first();
        $this->assertNotNull($childRow, 'The newly added child must have been charged as a genuinely new rate group.');
    }

    public function test_update_accepts_client_supplied_legacy_rate_id_directly(): void
    {
        // Bokun exposes our raw TourPackageRate id to the OTA as pricingCategoryId
        // (BokunRepository::productGetAvailable, 'pricingCategoryId' => (string) $group->id),
        // so a real OCTO client may still hold that old, Bokun-scoped id for a pre-cutover
        // booking with no reason to know it changed — it never called GET to relearn it.
        $bokunAffiliateId = $this->createBokunAffiliate();
        $octoAffiliateId  = $this->createOctoAffiliate();
        $packageId        = $this->createPackage($octoAffiliateId);
        $bokunAdultRateId = $this->createRate($packageId, $bokunAffiliateId, 1, ['rate_for' => 'Adult']);
        $octoAdultRateId  = $this->createRate($packageId, $octoAffiliateId, 1, ['rate_for' => 'Adult']);
        $octoChildRateId  = $this->createRate($packageId, $octoAffiliateId, 2, ['rate_for' => 'Child', 'age_from' => 4, 'age_to' => 17, 'rate' => 40.00]);
        $slot             = $this->createBookableSlot($packageId, $octoAffiliateId, 10);

        $tourBookingId = $this->createLegacyBokunBooking($packageId, $slot, $bokunAffiliateId, $bokunAdultRateId, 'HEAD', '100008', ['tourists' => 2]);

        // No prior GET — the update call itself must trigger backfill AND accept the raw
        // legacy rate id ('48'-equivalent) for the 2 existing adults, plus a genuinely new
        // OCTO-side id for the added child, in the very same request.
        $response = $this->withHeaders($this->octoHeaders())->patchJson('/api/v1/octo/bookings/100008', [
            'unitItems' => [
                ['unitId' => (string) $bokunAdultRateId],
                ['unitId' => (string) $bokunAdultRateId],
                ['unitId' => (string) $octoChildRateId],
            ],
        ]);
        $this->trackOctoBookingFor($tourBookingId);

        $response->assertStatus(200);

        $refundCount = DB::connection('tenant')->table('transactions')->where('tour_booking_id', $tourBookingId)->where('status', 'Refund')->count();
        $this->assertSame(0, $refundCount, 'Submitting the legacy id for unchanged adults must not be treated as removing them.');

        $adultRow = DB::connection('tenant')->table('tour_bookings_transact')
            ->where('tour_booking_id', $tourBookingId)->where('tour_package_rate_id', $octoAdultRateId)->first();
        $this->assertNotNull($adultRow, 'The submitted legacy adult id must have been translated to the OCTO rate id.');
        $this->assertEquals(2, $adultRow->tourists);

        $unitItems = json_decode(DB::connection('tenant')->table('octo_bookings')->where('tour_booking_id', $tourBookingId)->value('unit_items'), true);
        foreach ($unitItems as $item) {
            $this->assertNotSame((string) $bokunAdultRateId, $item['unitId'], 'Stored unit_items must never retain the raw legacy rate id.');
        }
    }

    public function test_translate_rate_id_rejects_a_rate_from_an_unrelated_package(): void
    {
        $bokunAffiliateId  = $this->createBokunAffiliate();
        $octoAffiliateId   = $this->createOctoAffiliate();
        $packageId         = $this->createPackage($octoAffiliateId);
        $otherPackageId    = $this->createPackage($octoAffiliateId);
        $bokunAdultRateId  = $this->createRate($packageId, $bokunAffiliateId, 1, ['rate_for' => 'Adult']);
        $this->createRate($packageId, $octoAffiliateId, 1, ['rate_for' => 'Adult']);
        // Same mapping_key (1) but under a completely different package — must not match.
        $unrelatedRateId = $this->createRate($otherPackageId, $bokunAffiliateId, 1, ['rate_for' => 'Adult']);
        $slot            = $this->createBookableSlot($packageId, $octoAffiliateId, 10);

        $tourBookingId = $this->createLegacyBokunBooking($packageId, $slot, $bokunAffiliateId, $bokunAdultRateId, 'HEAD', '100009', ['tourists' => 2]);

        $response = $this->withHeaders($this->octoHeaders())->patchJson('/api/v1/octo/bookings/100009', [
            'unitItems' => [
                ['unitId' => (string) $unrelatedRateId],
                ['unitId' => (string) $unrelatedRateId],
            ],
        ]);
        $this->trackOctoBookingFor($tourBookingId);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'INVALID_UNIT_ID']);
    }
}
