<?php

namespace Tests\Unit\Octo;

use App\Services\Octo\OctoService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Integration tests for OctoRepository endpoints.
 *
 * Run with:
 *   docker exec -it nat-app php artisan test tests/Unit/Octo/OctoRepositoryTest.php
 *
 * Safety note: DatabaseTransactions cannot be used here because OctoAuthentication
 * middleware calls DB::purge() + DB::reconnect() on every request, which destroys
 * the transaction connection. Manual cleanup arrays are used instead — tearDown()
 * always runs in PHPUnit even after test failures, so test data is reliably removed.
 * For 100% safety against process kills, use a dedicated test tenant database.
 *
 * The createBooking/confirmBooking/cancelBooking success-path tests below write real
 * rows to tour_slot_times, tour_slot_times_affiliate, affiliate_slot_times,
 * tour_bookings_master, tour_bookings_transact, transactions, tour_booking_history,
 * tour_slot_summaries, and audits (via TourBooking's Auditable trait) — all in
 * tenant_dixies on the local dev DB (see .env: DB_HOST=127.0.0.1, APP_ENV=local; this
 * suite never touches the live dixies.nativeamericantours.com server). Every row is
 * tracked in the cleanup*Ids arrays above and deleted in tearDown(), in dependency
 * order (booking children → booking masters → slot/seat fixtures → rates/packages/
 * customers), so no fixture data is left behind even if a test fails mid-way.
 */
class OctoRepositoryTest extends TestCase
{
    private const TEST_KEY      = 'octo-test-key-repository';
    private const RESELLER_NAME = 'octo-test-reseller';
    private const TEST_TENANT   = 'dixies'; // maps to tenant_dixies database

    protected array $cleanupCustomerIds = [];
    protected array $cleanupPackageIds  = [];
    protected array $cleanupRateIds     = [];

    // Fixtures for slot/seat setup used by createBooking/confirmBooking/cancelBooking
    // success-path tests, plus the tour_bookings_master rows those flows create.
    // All are local-DB-only rows (see class docblock) and are deleted in tearDown().
    protected array $cleanupSlotIds        = [];
    protected array $cleanupSlotDateIds    = [];
    protected array $cleanupTstaIds        = [];
    protected array $cleanupAstIds         = [];
    protected array $cleanupTourBookingIds = [];

    // Booking UUIDs accumulate across ALL tests and are deleted once after the
    // full suite — so a booking created in one test is still findable by later
    // tests in the same run (e.g. extend → confirm → cancel flow).
    private static array $allBookingUuids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        // Pre-configure the 'tenant' connection to point to the test tenant database,
        // mirroring what OctoAuthentication::switchTenantDatabase() does at request time.
        // This ensures test helpers that insert data before the request use the same DB
        // that the middleware will switch to during the request.
        $base = config('database.connections.mysql');
        Config::set('database.connections.tenant', array_merge($base, [
            'database' => 'tenant_' . self::TEST_TENANT,
        ]));
        DB::purge('tenant');

        // Insert a real API key into octo_keys (central DB) so OctoAuthentication
        // can resolve the bearer token via its sha256 hash lookup.
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
        // Delete deepest-first: tour_bookings_master children, then the master rows
        // themselves, then the slot/seat fixtures, then rates/packages/customers.
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

        if (!empty($this->cleanupSlotIds)) {
            DB::connection('tenant')->table('tour_slot_summaries')->whereIn('tour_slot_id', $this->cleanupSlotIds)->delete();
        }
        if (!empty($this->cleanupAstIds)) {
            DB::connection('tenant')->table('affiliate_slot_times')->whereIn('id', $this->cleanupAstIds)->delete();
        }
        if (!empty($this->cleanupTstaIds)) {
            DB::connection('tenant')->table('tour_slot_times_affiliate')->whereIn('id', $this->cleanupTstaIds)->delete();
        }
        if (!empty($this->cleanupSlotIds)) {
            DB::connection('tenant')->table('tour_slot_times')->whereIn('id', $this->cleanupSlotIds)->delete();
        }
        if (!empty($this->cleanupSlotDateIds)) {
            DB::connection('tenant')->table('tour_slot_dates')->whereIn('id', $this->cleanupSlotDateIds)->delete();
        }

        if (!empty($this->cleanupRateIds)) {
            DB::connection('tenant')->table('tour_package_rates')->whereIn('id', $this->cleanupRateIds)->delete();
        }
        if (!empty($this->cleanupPackageIds)) {
            DB::connection('tenant')->table('tour_packages')->whereIn('id', $this->cleanupPackageIds)->delete();
        }
        if (!empty($this->cleanupCustomerIds)) {
            DB::connection('tenant')->table('customers')->whereIn('id', $this->cleanupCustomerIds)->delete();
            DB::connection('tenant')->table('package_affiliate_assignments')
                ->whereIn('affiliate_id', $this->cleanupCustomerIds)
                ->delete();
        }

        // Remove the test API key from the central DB
        DB::connection('mysql')->table('octo_keys')
            ->where('api_key_hash', hash('sha256', self::TEST_KEY))
            ->delete();

        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        // Delete all test bookings once — after every test in the class has run.
        if (!empty(self::$allBookingUuids)) {
            \Illuminate\Support\Facades\DB::connection('tenant')
                ->table('octo_bookings')
                ->whereIn('uuid', self::$allBookingUuids)
                ->delete();
            self::$allBookingUuids = [];
        }

        parent::tearDownAfterClass();
    }

    private function octoHeaders(string $capabilities = ''): array
    {
        return [
            'Authorization'     => 'Bearer ' . self::TEST_KEY,
            'Octo-Capabilities' => $capabilities,
            'Accept'            => 'application/json',
        ];
    }

    private function createAffiliate(array $overrides = []): int
    {
        $operatorId = DB::connection('tenant')->table('tour_operators')->value('id');
        $this->assertNotNull($operatorId, 'Test requires at least one tour_operator row in tenant_' . self::TEST_TENANT . '.');

        $id = DB::connection('tenant')->table('customers')->insertGetId(array_merge([
            'tour_operator_id' => $operatorId,
            'name'             => self::RESELLER_NAME,
            'email'            => 'octo-test-' . uniqid() . '@example.com',
            'phone_number'     => '0000000000',
            'is_affiliate'     => 'Yes',
            'is_ota'           => 1,
            'ota_id'           => self::RESELLER_NAME,
            'status'           => 'Active',
            'created_at'       => Carbon::now(),
            'updated_at'       => Carbon::now(),
        ], $overrides));

        $this->cleanupCustomerIds[] = $id;
        return $id;
    }

    private function createPackageForAffiliate(int $affiliateId, array $overrides = []): int
    {
        $operatorId = DB::connection('tenant')->table('tour_operators')->value('id');

        $packageId = DB::connection('tenant')->table('tour_packages')->insertGetId(array_merge([
            'tour_operator_id'              => $operatorId,
            'name'                          => 'OCTO Test Package ' . uniqid(),
            'status'                        => 'Active',
            'type'                          => 'Regular',
            'service_commission_percentage' => 0,
            'stripe_commission_percentage'  => 0,
            'created_by'                    => 1,
            'created_by_user_type'          => 'Admin',
            'created_at'                    => Carbon::now(),
            'updated_at'                    => Carbon::now(),
        ], $overrides));

        $this->cleanupPackageIds[] = $packageId;

        DB::connection('tenant')->table('package_affiliate_assignments')->insert([
            'affiliate_id' => $affiliateId,
            'package_id'   => $packageId,
            'created_at'   => Carbon::now(),
            'updated_at'   => Carbon::now(),
        ]);

        return $packageId;
    }

    // ── GET /supplier ─────────────────────────────────────────────────────────

    public function test_get_supplier_returns_200_with_required_fields(): void
    {
        $response = $this->withHeaders($this->octoHeaders())->getJson('/api/v1/octo/supplier');

        $response->assertStatus(200);
        $response->assertJsonStructure(['id', 'name', 'endpoint', 'contact']);
    }

    // public function test_get_supplier_with_content_capability_includes_extra_fields(): void
    // {
    //     $response = $this->withHeaders($this->octoHeaders('content'))->getJson('/api/v1/octo/supplier');

    //     $response->assertStatus(200);
    //     $response->assertJsonStructure(['id', 'name', 'endpoint', 'contact', 'shortDescription', 'media']);
    // }

    // ── GET /products ─────────────────────────────────────────────────────────

    public function test_get_products_returns_400_when_no_packages_assigned(): void
    {
        $this->createAffiliate(); // affiliate exists but has no packages

        $response = $this->withHeaders($this->octoHeaders())->getJson('/api/v1/octo/products');

        // OctoErrorResponse::unprocessableEntity returns HTTP 400 per OCTO spec
        $response->assertStatus(400);
        $response->assertJson(['error' => 'UNPROCESSABLE_ENTITY']);
    }

    public function test_get_products_returns_product_list(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);

        $response = $this->withHeaders($this->octoHeaders())->getJson('/api/v1/octo/products');

        $response->assertStatus(200);
        $response->assertJsonIsArray();

        $ids = collect($response->json())->pluck('id')->all();
        $this->assertContains((string) $packageId, $ids);
    }

    // ── GET /products/{productId} ─────────────────────────────────────────────

    public function test_get_product_by_id_returns_400_for_unassigned_product(): void
    {
        $this->createAffiliate();

        $response = $this->withHeaders($this->octoHeaders())->getJson('/api/v1/octo/products/999999');

        $response->assertStatus(400);
        $response->assertJson(['error' => 'INVALID_PRODUCT_ID']);
    }

    public function test_get_product_by_id_returns_product_structure(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);

        $response = $this->withHeaders($this->octoHeaders())->getJson('/api/v1/octo/products/' . $packageId);

        $response->assertStatus(200);
        $response->assertJsonStructure(['id', 'internalName', 'options', 'availabilityType', 'locale', 'timeZone']);
        $response->assertJsonPath('id', (string) $packageId);
    }

    // ── POST /availability — date validation ──────────────────────────────────

    public function test_availability_rejects_missing_product_id(): void
    {
        $this->createAffiliate();

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/availability', [
            'optionId'       => 'default',
            'localDateStart' => '2025-06-01',
            'localDateEnd'   => '2025-06-30',
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'INVALID_PRODUCT_ID']);
    }

    public function test_availability_rejects_missing_option_id(): void
    {
        $this->createAffiliate();

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/availability', [
            'productId'      => '1',
            'localDateStart' => '2025-06-01',
            'localDateEnd'   => '2025-06-30',
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'INVALID_OPTION_ID']);
    }

    public function test_availability_rejects_malformed_start_date(): void
    {
        $this->createAffiliate();

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/availability', [
            'productId'      => '1',
            'optionId'       => 'default',
            'localDateStart' => 'not-a-date',
            'localDateEnd'   => '2025-06-30',
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'BAD_REQUEST']);
        $this->assertStringContainsString('localDateStart', $response->json('errorMessage'));
    }

    public function test_availability_rejects_malformed_end_date(): void
    {
        $this->createAffiliate();

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/availability', [
            'productId'      => '1',
            'optionId'       => 'default',
            'localDateStart' => '2025-06-01',
            'localDateEnd'   => '06/30/2025',
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'BAD_REQUEST']);
        $this->assertStringContainsString('localDateEnd', $response->json('errorMessage'));
    }

    public function test_availability_rejects_unknown_product(): void
    {
        $this->createAffiliate();

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/availability', [
            'productId'      => '999999',
            'optionId'       => 'default',
            'localDateStart' => '2025-06-01',
            'localDateEnd'   => '2025-06-30',
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'INVALID_PRODUCT_ID']);
    }

    // ── POST /availability/calendar — date validation ─────────────────────────

    public function test_calendar_rejects_malformed_dates(): void
    {
        $this->createAffiliate();

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/availability/calendar', [
            'productId'      => '1',
            'optionId'       => 'default',
            'localDateStart' => '2025/06/01',
            'localDateEnd'   => '2025-06-30',
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'BAD_REQUEST']);
    }

    // ── POST /availability & /availability/calendar — success paths ───────────
    // Use a real bookable slot (createBookableSlot) so the underlying OctoService
    // availability/pricing formulas are exercised end-to-end, not just validation.

    public function test_get_availability_calendar_returns_available_status_for_open_slot(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $this->createRateForPackage($packageId, $affiliateId);
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 10);

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/availability/calendar', [
            'productId'      => (string) $packageId,
            'optionId'       => 'default',
            'localDateStart' => Carbon::now()->addDays(40)->format('Y-m-d'),
            'localDateEnd'   => Carbon::now()->addDays(50)->format('Y-m-d'),
        ]);

        $response->assertStatus(200);
        $day = collect($response->json())->firstWhere('localDate', $slot->date);
        $this->assertNotNull($day, 'Expected the fixture slot date to appear in the calendar range.');
        $this->assertTrue($day['available']);
        $this->assertSame(10, $day['vacancies']);
        $this->assertSame('AVAILABLE', $day['status']);
    }

    public function test_get_availability_calendar_reflects_limited_status_after_booking(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $rateId      = $this->createRateForPackage($packageId, $affiliateId);
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 10);

        // Consume 6 of 10 allocated seats — leaves 4, which is <= capacity/2 => LIMITED.
        $this->createAndConfirmBooking($packageId, $rateId, $slot, null, 6);

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/availability/calendar', [
            'productId'      => (string) $packageId,
            'optionId'       => 'default',
            'localDateStart' => Carbon::now()->addDays(40)->format('Y-m-d'),
            'localDateEnd'   => Carbon::now()->addDays(50)->format('Y-m-d'),
        ]);

        $response->assertStatus(200);
        $day = collect($response->json())->firstWhere('localDate', $slot->date);
        $this->assertSame(4, $day['vacancies']);
        $this->assertSame('LIMITED', $day['status']);
    }

    public function test_get_availability_calendar_includes_pricing_when_capability_active(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $this->createRateForPackage($packageId, $affiliateId);
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 10);

        $response = $this->withHeaders($this->octoHeaders('pricing'))->postJson('/api/v1/octo/availability/calendar', [
            'productId'      => (string) $packageId,
            'optionId'       => 'default',
            'localDateStart' => Carbon::now()->addDays(40)->format('Y-m-d'),
            'localDateEnd'   => Carbon::now()->addDays(50)->format('Y-m-d'),
        ]);

        $response->assertStatus(200);
        $day = collect($response->json())->firstWhere('localDate', $slot->date);
        $this->assertArrayHasKey('pricingFrom', $day);
        $this->assertNotEmpty($day['pricingFrom']);
    }

    public function test_get_availability_check_returns_slot_entries_for_date_range(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $this->createRateForPackage($packageId, $affiliateId);
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 10);

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/availability', [
            'productId'      => (string) $packageId,
            'optionId'       => 'default',
            'localDateStart' => Carbon::now()->addDays(40)->format('Y-m-d'),
            'localDateEnd'   => Carbon::now()->addDays(50)->format('Y-m-d'),
        ]);

        $response->assertStatus(200);
        $entry = collect($response->json())->firstWhere('id', (string) $slot->id);
        $this->assertNotNull($entry, 'Expected the fixture slot to appear in the availability response.');
        $this->assertTrue($entry['available']);
        $this->assertSame(10, $entry['vacancies']);
        $this->assertSame('AVAILABLE', $entry['status']);
    }

    /**
     * Regression test: when the querying affiliate has infant rates enabled, another
     * customer's booking with infants on the same slot must NOT have its infants
     * subtracted from the *global* seat count. Global capacity always tracks
     * tourists only (see OctoService::resolveRemainingSeats), regardless of any
     * affiliate's infant-rate flag — only the affiliate's own booked count adds
     * infants. Before the fix, fetchAffiliateSlotRows() leaked the querying
     * affiliate's infant-rate flag into the global count too, over-subtracting
     * capacity for bookings it doesn't even own.
     */
    public function test_get_availability_check_global_booked_excludes_infants_of_other_customers(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        // Gives this affiliate an active infant rate, which flips on infant-counting
        // for its OWN booked seats (hasInfantRates() === true).
        $this->createRateForPackage($packageId, $affiliateId, ['rate_for' => 'Infant', 'is_infant_rate' => 1]);
        // Slot's own capacity (6) is tighter than the affiliate's seat limit (10),
        // so the global count — not the affiliate count — is what binds here.
        $slot = $this->createBookableSlot($packageId, $affiliateId, 10, ['time_slot_seats' => 6]);

        // A different customer (not this affiliate) books 2 adults + 3 infants on
        // the same slot. This affiliate made no booking at all.
        $otherCustomerId = DB::connection('tenant')->table('customers')->insertGetId([
            'tour_operator_id' => DB::connection('tenant')->table('tour_operators')->value('id'),
            'name'             => 'Other Walk-in Customer',
            'email'            => 'octo-other-' . uniqid() . '@example.com',
            'phone_number'     => '0000000000',
            'is_affiliate'     => 'No',
            'status'           => 'Active',
            'created_at'       => Carbon::now(),
            'updated_at'       => Carbon::now(),
        ]);
        $this->cleanupCustomerIds[] = $otherCustomerId;

        $otherBookingId = DB::connection('tenant')->table('tour_bookings_master')->insertGetId([
            'tour_package_id'      => $packageId,
            'tour_slot_id'         => $slot->id,
            'customer_id'          => $otherCustomerId,
            'name'                 => 'Other Walk-in Customer',
            'email'                => 'octo-other-booking-' . uniqid() . '@example.com',
            'phone_number'         => '',
            'tourists'             => 2,
            'infants'              => 3,
            'comments'             => '',
            'note'                 => '',
            'mask_required'        => 'Yes',
            'total'                => 200,
            'booking_total'        => 200,
            'service_commission'   => 0,
            'status'               => 'Booked',
            'created_by'           => $otherCustomerId,
            'created_by_user_type' => 'Customer',
            'created_at'           => Carbon::now(),
            'updated_at'           => Carbon::now(),
        ]);
        $this->cleanupTourBookingIds[] = $otherBookingId;

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/availability', [
            'productId'      => (string) $packageId,
            'optionId'       => 'default',
            'localDateStart' => Carbon::now()->addDays(40)->format('Y-m-d'),
            'localDateEnd'   => Carbon::now()->addDays(50)->format('Y-m-d'),
        ]);

        $response->assertStatus(200);
        $entry = collect($response->json())->firstWhere('id', (string) $slot->id);
        $this->assertNotNull($entry, 'Expected the fixture slot to appear in the availability response.');
        // slot capacity 6, global_booked must be tourists-only (2) => 4 remaining.
        // Pre-fix this leaked infants into global_booked (2+3=5), leaving only 1.
        $this->assertSame(4, $entry['vacancies']);
        $this->assertSame('AVAILABLE', $entry['status']);
    }

    public function test_get_availability_check_includes_pricing_when_capability_active(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $this->createRateForPackage($packageId, $affiliateId);
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 10);

        $response = $this->withHeaders($this->octoHeaders('pricing'))->postJson('/api/v1/octo/availability', [
            'productId'      => (string) $packageId,
            'optionId'       => 'default',
            'localDateStart' => Carbon::now()->addDays(40)->format('Y-m-d'),
            'localDateEnd'   => Carbon::now()->addDays(50)->format('Y-m-d'),
        ]);

        $response->assertStatus(200);
        $entry = collect($response->json())->firstWhere('id', (string) $slot->id);
        $this->assertArrayHasKey('pricing', $entry);
    }

    public function test_get_availability_check_by_ids_returns_only_requested_slots(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $this->createRateForPackage($packageId, $affiliateId);
        $slotA       = $this->createBookableSlot($packageId, $affiliateId, 10);
        $slotB       = $this->createBookableSlot($packageId, $affiliateId, 10, ['time_from' => '14:00:00', 'time_to' => '16:00:00']);

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/availability', [
            'productId'       => (string) $packageId,
            'optionId'        => 'default',
            'availabilityIds' => [(string) $slotA->id],
        ]);

        $response->assertStatus(200);
        $this->assertSame([(string) $slotA->id], collect($response->json())->pluck('id')->all());
    }

    public function test_get_availability_check_rejects_mutually_exclusive_filters(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/availability', [
            'productId'       => (string) $packageId,
            'optionId'        => 'default',
            'availabilityIds' => ['1'],
            'localDateStart'  => Carbon::now()->addDays(1)->format('Y-m-d'),
            'localDateEnd'    => Carbon::now()->addDays(2)->format('Y-m-d'),
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'BAD_REQUEST']);
    }

    // ── GET /bookings — listBookings ────────────────────────────────────────────

    public function test_list_bookings_requires_reseller_or_supplier_reference(): void
    {
        $this->createAffiliate();

        $response = $this->withHeaders($this->octoHeaders())->getJson('/api/v1/octo/bookings');

        $response->assertStatus(400);
        $response->assertJson(['error' => 'BAD_REQUEST']);
    }

    public function test_list_bookings_filters_by_reseller_reference(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $rateId      = $this->createRateForPackage($packageId, $affiliateId);
        $slotA       = $this->createBookableSlot($packageId, $affiliateId, 10);
        $slotB       = $this->createBookableSlot($packageId, $affiliateId, 10, ['time_from' => '14:00:00', 'time_to' => '16:00:00']);

        $uuidA = $this->createAndConfirmBooking($packageId, $rateId, $slotA, 'RESELLER-REF-AAA');
        $uuidB = $this->createAndConfirmBooking($packageId, $rateId, $slotB, 'RESELLER-REF-BBB');

        $response = $this->withHeaders($this->octoHeaders())->getJson('/api/v1/octo/bookings?resellerReference=RESELLER-REF-AAA');

        $response->assertStatus(200);
        $uuids = collect($response->json())->pluck('uuid')->all();
        $this->assertContains($uuidA, $uuids);
        $this->assertNotContains($uuidB, $uuids);
    }

    public function test_list_bookings_filters_by_supplier_reference(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $rateId      = $this->createRateForPackage($packageId, $affiliateId);
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 10);

        $uuid = $this->createAndConfirmBooking($packageId, $rateId, $slot);

        $octoBookingId      = DB::connection('tenant')->table('octo_bookings')->where('uuid', $uuid)->value('id');
        $supplierReference  = 'OCTO-' . str_pad((string) $octoBookingId, 6, '0', STR_PAD_LEFT);

        $response = $this->withHeaders($this->octoHeaders())
            ->getJson('/api/v1/octo/bookings?supplierReference=' . $supplierReference);

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.uuid', $uuid);
    }

    public function test_list_bookings_all_zero_supplier_reference_returns_empty_array(): void
    {
        $this->createAffiliate();

        $response = $this->withHeaders($this->octoHeaders())->getJson('/api/v1/octo/bookings?supplierReference=OCTO-000000');

        $response->assertStatus(200);
        $response->assertExactJson([]);
    }

    public function test_list_bookings_includes_pricing_when_capability_active(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $rateId      = $this->createRateForPackage($packageId, $affiliateId);
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 10);

        $uuid = $this->createAndConfirmBooking($packageId, $rateId, $slot, 'RESELLER-PRICING-1');

        $response = $this->withHeaders($this->octoHeaders('pricing'))
            ->getJson('/api/v1/octo/bookings?resellerReference=RESELLER-PRICING-1');

        $response->assertStatus(200);
        $response->assertJsonPath('0.uuid', $uuid);
        $this->assertArrayHasKey('pricing', $response->json('0'));
    }

    // ── POST /bookings — validation ───────────────────────────────────────────

    public function test_create_booking_without_uuid_proceeds_to_product_validation(): void
    {
        // uuid is optional — auto-generated when omitted. Request proceeds to product validation.
        $this->createAffiliate();

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', [
            'productId'      => '999999',
            'optionId'       => 'default',
            'availabilityId' => '1',
            'unitItems'      => [['unitId' => '1']],
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'INVALID_PRODUCT_ID']);
    }

    public function test_create_booking_with_non_default_option_id_returns_error(): void
    {
        $this->createAffiliate();

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', [
            'uuid'           => 'a1b2c3d4-0000-0000-0000-000000000001',
            'productId'      => '1',
            'optionId'       => 'premium',
            'availabilityId' => '1',
            'unitItems'      => [['unitId' => '1']],
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'INVALID_OPTION_ID']);
    }

    public function test_create_booking_rejects_missing_product_id(): void
    {
        $this->createAffiliate();

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', [
            'uuid'           => 'a1b2c3d4-0000-0000-0000-000000000001',
            'optionId'       => 'default',
            'availabilityId' => '1',
            'unitItems'      => [['uuid' => 'a1b2c3d4-0000-0000-0000-000000000002', 'unitId' => '1']],
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'INVALID_PRODUCT_ID']);
    }

    public function test_create_booking_rejects_missing_option_id(): void
    {
        $this->createAffiliate();

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', [
            'uuid'           => 'a1b2c3d4-0000-0000-0000-000000000001',
            'productId'      => '1',
            'availabilityId' => '1',
            'unitItems'      => [['uuid' => 'a1b2c3d4-0000-0000-0000-000000000002', 'unitId' => '1']],
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'INVALID_OPTION_ID']);
    }

    public function test_create_booking_rejects_missing_availability_id(): void
    {
        $this->createAffiliate();

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', [
            'uuid'      => 'a1b2c3d4-0000-0000-0000-000000000001',
            'productId' => '1',
            'optionId'  => 'default',
            'unitItems' => [['uuid' => 'a1b2c3d4-0000-0000-0000-000000000002', 'unitId' => '1']],
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'INVALID_AVAILABILITY_ID']);
    }

    public function test_create_booking_rejects_empty_unit_items(): void
    {
        $this->createAffiliate();

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', [
            'uuid'           => 'a1b2c3d4-0000-0000-0000-000000000001',
            'productId'      => '1',
            'optionId'       => 'default',
            'availabilityId' => '1',
            'unitItems'      => [],
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'UNPROCESSABLE_ENTITY']);
    }

    public function test_create_booking_returns_400_for_unknown_product(): void
    {
        $this->createAffiliate();

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', [
            'uuid'           => 'a1b2c3d4-0000-0000-0000-000000000001',
            'productId'      => '999999',
            'optionId'       => 'default',
            'availabilityId' => '1',
            'unitItems'      => [['uuid' => 'a1b2c3d4-0000-0000-0000-000000000002', 'unitId' => '1']],
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'INVALID_PRODUCT_ID']);
    }

    // ── POST /bookings — success path & seat/pax enforcement ──────────────────
    // These use a real tour_slot_times row (via createBookableSlot()) instead of the
    // fake tour_slot_id=999999 fixture above, since createBooking needs a genuine slot
    // to validate against. All fixture rows are deleted in tearDown().

    public function test_create_booking_creates_new_on_hold_booking_with_valid_data(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $rateId      = $this->createRateForPackage($packageId, $affiliateId);
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 10);

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', [
            'productId'      => (string) $packageId,
            'optionId'       => 'default',
            'availabilityId' => (string) $slot->id,
            'unitItems'      => [['unitId' => (string) $rateId]],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'ON_HOLD');
        $response->assertJsonPath('availabilityId', (string) $slot->id);

        $uuid = $response->json('uuid');
        $this->assertNotEmpty($uuid);
        self::$allBookingUuids[] = $uuid;

        $this->assertSame(
            1,
            DB::connection('tenant')->table('octo_bookings')->where('uuid', $uuid)->where('tourists', 1)->count()
        );
    }

    public function test_create_booking_rejects_a_non_uuid_idempotency_key(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $rateId      = $this->createRateForPackage($packageId, $affiliateId);
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 10);

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', [
            'uuid'           => 'order-12345',
            'productId'      => (string) $packageId,
            'optionId'       => 'default',
            'availabilityId' => (string) $slot->id,
            'unitItems'      => [['unitId' => (string) $rateId]],
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'BAD_REQUEST');

        $this->assertSame(
            0,
            DB::connection('tenant')->table('octo_bookings')->where('uuid', 'order-12345')->count(),
            'A rejected idempotency key must not reserve anything.'
        );
    }

    public function test_create_booking_rejects_a_replayed_uuid_carrying_different_details(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $rateId      = $this->createRateForPackage($packageId, $affiliateId);
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 10);

        $uuid    = (string) Str::uuid();
        $payload = [
            'uuid'           => $uuid,
            'productId'      => (string) $packageId,
            'optionId'       => 'default',
            'availabilityId' => (string) $slot->id,
            'unitItems'      => [['unitId' => (string) $rateId]],
        ];

        $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', $payload)->assertStatus(200);
        self::$allBookingUuids[] = $uuid;

        // Same key, two guests instead of one — a different booking, so it must conflict
        // rather than silently resolve to the original.
        $payload['unitItems'] = [['unitId' => (string) $rateId], ['unitId' => (string) $rateId]];
        $replay = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', $payload);

        $replay->assertStatus(409);
        $replay->assertJsonPath('error', 'BOOKING_UUID_CONFLICT');

        $this->assertSame(
            1,
            (int) DB::connection('tenant')->table('octo_bookings')->where('uuid', $uuid)->value('tourists'),
            'The original hold must be left exactly as it was.'
        );
    }

    public function test_create_booking_does_not_disclose_another_affiliates_booking_via_a_replayed_uuid(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $rateId      = $this->createRateForPackage($packageId, $affiliateId);
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 10);

        $uuid    = (string) Str::uuid();
        $payload = [
            'uuid'           => $uuid,
            'productId'      => (string) $packageId,
            'optionId'       => 'default',
            'availabilityId' => (string) $slot->id,
            'unitItems'      => [['unitId' => (string) $rateId]],
        ];

        $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', $payload)->assertStatus(200);
        self::$allBookingUuids[] = $uuid;

        // Re-point the API key's affiliate lookup at a different reseller, then replay
        // the first reseller's uuid.
        $otherAffiliateId = $this->createAffiliate();
        DB::connection('tenant')->table('customers')->where('id', $affiliateId)->update(['ota_id' => 'displaced-' . uniqid()]);
        DB::connection('tenant')->table('customers')->where('id', $otherAffiliateId)->update(['ota_id' => self::RESELLER_NAME]);

        $replay = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', $payload);

        $replay->assertStatus(409);
        $replay->assertJsonPath('error', 'BOOKING_UUID_CONFLICT');
        $replay->assertJsonMissingPath('contact');
        $replay->assertJsonMissingPath('status');
    }

    public function test_create_booking_resolves_a_concurrent_duplicate_uuid_instead_of_erroring(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $rateId      = $this->createRateForPackage($packageId, $affiliateId);
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 10);

        $uuid = (string) Str::uuid();
        self::$allBookingUuids[] = $uuid;

        // Simulate the race deterministically: the moment createBooking has finished its
        // "does this uuid exist?" lookup and found nothing, a competing request commits
        // the same uuid. The insert that follows then hits the unique index.
        $injected = false;
        DB::listen(function ($query) use (&$injected, $uuid, $affiliateId, $packageId, $slot, $rateId) {
            if ($injected) {
                return;
            }
            if (!str_contains($query->sql, 'octo_bookings') || !str_starts_with(trim($query->sql), 'select')) {
                return;
            }
            if (!in_array($uuid, $query->bindings, true)) {
                return;
            }

            $injected = true;
            DB::connection('tenant')->table('octo_bookings')->insert([
                'uuid'            => $uuid,
                'affiliate_id'    => $affiliateId,
                'tour_slot_id'    => $slot->id,
                'tour_package_id' => $packageId,
                'status'          => 'ON_HOLD',
                'tourists'        => 1,
                'infants'         => 0,
                // Same shape createBooking writes — the response formatter reads all of these.
                'unit_items'      => json_encode([[
                    'uuid'              => (string) Str::uuid(),
                    'resellerReference' => '',
                    'supplierReference' => 'RACEWN',
                    'unitId'            => (string) $rateId,
                    'contact'           => null,
                ]]),
                'contact'         => null,
                'notes'           => null,
                'expire_at'       => Carbon::now()->addMinutes(30),
                'created_at'      => Carbon::now(),
                'updated_at'      => Carbon::now(),
            ]);
        });

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', [
            'uuid'           => $uuid,
            'productId'      => (string) $packageId,
            'optionId'       => 'default',
            'availabilityId' => (string) $slot->id,
            'unitItems'      => [['unitId' => (string) $rateId]],
        ]);

        $this->assertTrue($injected, 'The competing insert did not fire — the race was not simulated.');

        $response->assertStatus(200);
        $response->assertJsonPath('uuid', $uuid);
        $this->assertSame(
            1,
            DB::connection('tenant')->table('octo_bookings')->where('uuid', $uuid)->count(),
            'Losing the race must resolve to the winning booking, not create a second one.'
        );
    }

    public function test_create_booking_is_idempotent_for_duplicate_uuid(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $rateId      = $this->createRateForPackage($packageId, $affiliateId);
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 10);

        $uuid    = (string) Str::uuid();
        $payload = [
            'uuid'           => $uuid,
            'productId'      => (string) $packageId,
            'optionId'       => 'default',
            'availabilityId' => (string) $slot->id,
            'unitItems'      => [['unitId' => (string) $rateId]],
        ];

        $first = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', $payload);
        $first->assertStatus(200);
        self::$allBookingUuids[] = $uuid;

        $second = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', $payload);
        $second->assertStatus(200);
        $second->assertJsonPath('uuid', $uuid);

        $this->assertSame(
            1,
            DB::connection('tenant')->table('octo_bookings')->where('uuid', $uuid)->count(),
            'Re-posting the same uuid must not create a second booking row.'
        );
    }

    public function test_create_booking_rejects_unknown_unit_id(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $this->createRateForPackage($packageId, $affiliateId);
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 10);

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', [
            'productId'      => (string) $packageId,
            'optionId'       => 'default',
            'availabilityId' => (string) $slot->id,
            'unitItems'      => [['unitId' => '9999999']],
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'INVALID_UNIT_ID']);
    }

    public function test_create_booking_enforces_affiliate_min_pax(): void
    {
        $affiliateId = $this->createAffiliate(['min_pax' => 3]);
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $rateId      = $this->createRateForPackage($packageId, $affiliateId);
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 10);

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', [
            'productId'      => (string) $packageId,
            'optionId'       => 'default',
            'availabilityId' => (string) $slot->id,
            'unitItems'      => [['unitId' => (string) $rateId]], // only 1 tourist, below min_pax=3
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'UNPROCESSABLE_ENTITY']);
        $this->assertStringContainsString('minimum of 3', $response->json('errorMessage'));
    }

    public function test_create_booking_enforces_affiliate_max_pax(): void
    {
        $affiliateId = $this->createAffiliate(['max_pax' => 2]);
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $rateId      = $this->createRateForPackage($packageId, $affiliateId);
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 10);

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', [
            'productId'      => (string) $packageId,
            'optionId'       => 'default',
            'availabilityId' => (string) $slot->id,
            'unitItems'      => [
                ['unitId' => (string) $rateId],
                ['unitId' => (string) $rateId],
                ['unitId' => (string) $rateId],
            ], // 3 tourists, above max_pax=2
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'UNPROCESSABLE_ENTITY']);
        $this->assertStringContainsString('maximum of 2', $response->json('errorMessage'));
    }

    public function test_create_booking_returns_error_when_no_seat_allocation_configured(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $rateId      = $this->createRateForPackage($packageId, $affiliateId);
        // seatLimit=null → tour_slot_times_affiliate row exists but no affiliate_slot_times row
        $slot        = $this->createBookableSlot($packageId, $affiliateId, null);

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', [
            'productId'      => (string) $packageId,
            'optionId'       => 'default',
            'availabilityId' => (string) $slot->id,
            'unitItems'      => [['unitId' => (string) $rateId]],
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'UNPROCESSABLE_ENTITY']);
        $this->assertStringContainsString('No seat allocation', $response->json('errorMessage'));
    }

    public function test_create_booking_rejects_when_not_enough_seats_available(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $rateId      = $this->createRateForPackage($packageId, $affiliateId);
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 1); // only 1 seat allocated

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', [
            'productId'      => (string) $packageId,
            'optionId'       => 'default',
            'availabilityId' => (string) $slot->id,
            'unitItems'      => [
                ['unitId' => (string) $rateId],
                ['unitId' => (string) $rateId],
            ], // 2 tourists requested against a 1-seat allocation
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'UNPROCESSABLE_ENTITY']);
        $this->assertStringContainsString('Not enough seats', $response->json('errorMessage'));

        $this->assertSame(
            0,
            DB::connection('tenant')->table('octo_bookings')->where('tour_slot_id', $slot->id)->count(),
            'A rejected booking must not leave a partial octo_bookings row behind.'
        );
    }

    public function test_create_booking_rejects_past_time_slot(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $rateId      = $this->createRateForPackage($packageId, $affiliateId, ['scheduling_year' => (int) Carbon::yesterday()->format('Y')]);
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 10, ['date' => Carbon::yesterday()->format('Y-m-d')]);

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', [
            'productId'      => (string) $packageId,
            'optionId'       => 'default',
            'availabilityId' => (string) $slot->id,
            'unitItems'      => [['unitId' => (string) $rateId]],
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'UNPROCESSABLE_ENTITY']);
        $this->assertStringContainsString('in the past', $response->json('errorMessage'));
    }

    public function test_create_booking_rejects_slot_within_ota_cutoff_window(): void
    {
        $affiliateId = $this->createAffiliate();
        // 72-hour cutoff, but the slot is only 1 day out — falls inside the cutoff window
        $packageId   = $this->createPackageForAffiliate($affiliateId, ['ota_duration' => 72]);
        $rateId      = $this->createRateForPackage($packageId, $affiliateId);
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 10, ['date' => Carbon::tomorrow()->format('Y-m-d')]);

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', [
            'productId'      => (string) $packageId,
            'optionId'       => 'default',
            'availabilityId' => (string) $slot->id,
            'unitItems'      => [['unitId' => (string) $rateId]],
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'UNPROCESSABLE_ENTITY']);
        $this->assertStringContainsString('cutoff window', $response->json('errorMessage'));
    }

    public function test_create_booking_rejects_closed_slot(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $rateId      = $this->createRateForPackage($packageId, $affiliateId);
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 10, ['bookable_status' => 'Closed']);

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', [
            'productId'      => (string) $packageId,
            'optionId'       => 'default',
            'availabilityId' => (string) $slot->id,
            'unitItems'      => [['unitId' => (string) $rateId]],
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'UNPROCESSABLE_ENTITY']);
        $this->assertStringContainsString('not available for booking', $response->json('errorMessage'));
    }

    // ── GET /bookings/{uuid} ──────────────────────────────────────────────────

    public function test_get_booking_returns_400_for_unknown_uuid(): void
    {
        $this->createAffiliate();

        $response = $this->withHeaders($this->octoHeaders())->getJson('/api/v1/octo/bookings/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(400);
        $response->assertJson(['error' => 'INVALID_BOOKING_UUID']);
    }

    // ── POST /bookings/{uuid}/confirm ─────────────────────────────────────────

    public function test_confirm_booking_returns_400_for_unknown_uuid(): void
    {
        $this->createAffiliate();

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings/00000000-0000-0000-0000-000000000000/confirm', [
            'contact' => [
                'firstName'    => 'John',
                'lastName'     => 'Doe',
                'emailAddress' => 'john@example.com',
                'phoneNumber'  => '+14805551234',
            ],
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'INVALID_BOOKING_UUID']);
    }

    public function test_confirm_booking_returns_409_for_cancelled_booking(): void
    {
        $affiliateId = $this->createAffiliate();
        $uuid        = $this->createOctoBooking($affiliateId, 'CANCELLED');

        $response = $this->withHeaders($this->octoHeaders())->postJson("/api/v1/octo/bookings/{$uuid}/confirm", [
            'contact' => ['firstName' => 'John', 'lastName' => 'Doe', 'emailAddress' => 'john@example.com'],
        ]);

        $response->assertStatus(409);
        $response->assertJson(['error' => 'BOOKING_ALREADY_CANCELLED']);
    }

    public function test_confirm_booking_returns_409_for_expired_booking(): void
    {
        $affiliateId = $this->createAffiliate();
        $uuid        = $this->createOctoBooking($affiliateId, 'ON_HOLD', [
            'expire_at' => Carbon::now()->subMinutes(5),
        ]);

        $response = $this->withHeaders($this->octoHeaders())->postJson("/api/v1/octo/bookings/{$uuid}/confirm", [
            'contact' => ['firstName' => 'John', 'lastName' => 'Doe', 'emailAddress' => 'john@example.com'],
        ]);

        $response->assertStatus(409);
        $response->assertJson(['error' => 'BOOKING_EXPIRED']);
    }

    public function test_confirm_booking_returns_400_for_missing_contact_fields(): void
    {
        $affiliateId = $this->createAffiliate();
        $uuid        = $this->createOctoBooking($affiliateId, 'ON_HOLD');

        $response = $this->withHeaders($this->octoHeaders())->postJson("/api/v1/octo/bookings/{$uuid}/confirm", [
            'contact' => ['firstName' => 'John'], // missing lastName and emailAddress
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'BAD_REQUEST']);
        $this->assertStringContainsString('contact.lastName', $response->json('errorMessage'));
        $this->assertStringContainsString('contact.emailAddress', $response->json('errorMessage'));
    }

    // ── POST /bookings/{uuid}/confirm — success path ───────────────────────────
    // Uses a real slot (createBookableSlot) + a real ON_HOLD booking (via the createBooking
    // endpoint) so confirmBooking's full tour_bookings_master/transact/transaction/history
    // write path is exercised. All resulting rows are tracked and deleted in tearDown().

    public function test_confirm_booking_creates_tour_booking_and_returns_confirmed_status(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $rateId      = $this->createRateForPackage($packageId, $affiliateId);
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 10);

        $create = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', [
            'productId'      => (string) $packageId,
            'optionId'       => 'default',
            'availabilityId' => (string) $slot->id,
            'unitItems'      => [['unitId' => (string) $rateId]],
        ]);
        $create->assertStatus(200);
        $uuid = $create->json('uuid');
        self::$allBookingUuids[] = $uuid;

        $confirm = $this->withHeaders($this->octoHeaders())->postJson("/api/v1/octo/bookings/{$uuid}/confirm", [
            'contact' => [
                'firstName'    => 'Jane',
                'lastName'     => 'Doe',
                'emailAddress' => 'jane@example.com',
                'phoneNumber'  => '+14805551234',
            ],
        ]);

        $confirm->assertStatus(200);
        $confirm->assertJsonPath('status', 'CONFIRMED');
        $confirm->assertJsonPath('uuid', $uuid);

        $tourBookingId = $this->trackConfirmedTourBooking($uuid);
        $this->assertNotNull($tourBookingId, 'confirmBooking must create a tour_bookings_master row.');

        $this->assertSame(1, DB::connection('tenant')->table('tour_bookings_master')
            ->where('id', $tourBookingId)->where('status', 'Booked')->where('tourists', 1)->count());
        $this->assertSame(1, DB::connection('tenant')->table('transactions')
            ->where('tour_booking_id', $tourBookingId)->where('status', 'Completed')->count());
        $this->assertGreaterThanOrEqual(1, DB::connection('tenant')->table('tour_booking_history')
            ->where('tour_booking_id', $tourBookingId)->count());
    }

    public function test_confirm_booking_is_idempotent_when_already_confirmed(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $rateId      = $this->createRateForPackage($packageId, $affiliateId);
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 10);

        $create = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', [
            'productId'      => (string) $packageId,
            'optionId'       => 'default',
            'availabilityId' => (string) $slot->id,
            'unitItems'      => [['unitId' => (string) $rateId]],
        ]);
        $uuid = $create->json('uuid');
        self::$allBookingUuids[] = $uuid;

        $contactPayload = ['contact' => [
            'firstName' => 'Jane', 'lastName' => 'Doe', 'emailAddress' => 'jane@example.com',
        ]];

        $firstConfirm = $this->withHeaders($this->octoHeaders())->postJson("/api/v1/octo/bookings/{$uuid}/confirm", $contactPayload);
        $firstConfirm->assertStatus(200);
        $tourBookingId = $this->trackConfirmedTourBooking($uuid);

        $secondConfirm = $this->withHeaders($this->octoHeaders())->postJson("/api/v1/octo/bookings/{$uuid}/confirm", $contactPayload);
        $secondConfirm->assertStatus(200);
        $secondConfirm->assertJsonPath('status', 'CONFIRMED');

        $this->assertSame(
            $tourBookingId,
            DB::connection('tenant')->table('octo_bookings')->where('uuid', $uuid)->value('tour_booking_id')
        );
        $this->assertSame(1, DB::connection('tenant')->table('tour_bookings_master')->where('id', $tourBookingId)->count());
        $this->assertSame(
            1,
            DB::connection('tenant')->table('transactions')->where('tour_booking_id', $tourBookingId)->count(),
            'Re-confirming an already-CONFIRMED booking must not create a duplicate transaction.'
        );
    }

    public function test_confirm_booking_rejects_invalid_unit_id_in_replacement_unit_items(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $rateId      = $this->createRateForPackage($packageId, $affiliateId);
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 10);

        $create = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', [
            'productId'      => (string) $packageId,
            'optionId'       => 'default',
            'availabilityId' => (string) $slot->id,
            'unitItems'      => [['unitId' => (string) $rateId]],
        ]);
        $uuid = $create->json('uuid');
        self::$allBookingUuids[] = $uuid;

        $confirm = $this->withHeaders($this->octoHeaders())->postJson("/api/v1/octo/bookings/{$uuid}/confirm", [
            'contact'   => ['firstName' => 'Jane', 'lastName' => 'Doe', 'emailAddress' => 'jane@example.com'],
            'unitItems' => [['unitId' => '9999999']],
        ]);

        $confirm->assertStatus(400);
        $confirm->assertJson(['error' => 'INVALID_UNIT_ID']);

        // Booking must remain ON_HOLD — a rejected replacement must not partially apply.
        $this->assertSame('ON_HOLD', DB::connection('tenant')->table('octo_bookings')->where('uuid', $uuid)->value('status'));
    }

    public function test_confirm_booking_replaces_unit_items_and_updates_tourist_count(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $rateId      = $this->createRateForPackage($packageId, $affiliateId);
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 10);

        $create = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', [
            'productId'      => (string) $packageId,
            'optionId'       => 'default',
            'availabilityId' => (string) $slot->id,
            'unitItems'      => [['unitId' => (string) $rateId]],
        ]);
        $uuid = $create->json('uuid');
        self::$allBookingUuids[] = $uuid;

        $confirm = $this->withHeaders($this->octoHeaders())->postJson("/api/v1/octo/bookings/{$uuid}/confirm", [
            'contact'   => ['firstName' => 'Jane', 'lastName' => 'Doe', 'emailAddress' => 'jane@example.com'],
            'unitItems' => [
                ['unitId' => (string) $rateId],
                ['unitId' => (string) $rateId],
            ],
        ]);

        $confirm->assertStatus(200);
        $confirm->assertJsonPath('status', 'CONFIRMED');

        $tourBookingId = $this->trackConfirmedTourBooking($uuid);
        $this->assertSame(2, (int) DB::connection('tenant')->table('octo_bookings')->where('uuid', $uuid)->value('tourists'));
        $this->assertSame(2, (int) DB::connection('tenant')->table('tour_bookings_master')->where('id', $tourBookingId)->value('tourists'));
    }

    // ── POST /bookings/{uuid}/cancel ──────────────────────────────────────────

    public function test_cancel_booking_returns_400_for_unknown_uuid(): void
    {
        $this->createAffiliate();

        $response = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings/00000000-0000-0000-0000-000000000000/cancel', []);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'INVALID_BOOKING_UUID']);
    }

    public function test_cancel_booking_returns_409_for_expired_on_hold_booking(): void
    {
        $affiliateId = $this->createAffiliate();
        $uuid        = $this->createOctoBooking($affiliateId, 'ON_HOLD', [
            'expire_at' => Carbon::now()->subMinutes(5),
        ]);

        $response = $this->withHeaders($this->octoHeaders())->postJson("/api/v1/octo/bookings/{$uuid}/cancel", []);

        $response->assertStatus(409);
        $response->assertJson(['error' => 'BOOKING_EXPIRED']);
    }

    public function test_cancel_on_hold_booking_returns_200_with_cancelled_status(): void
    {
        $affiliateId = $this->createAffiliate();
        $uuid        = $this->createOctoBooking($affiliateId, 'ON_HOLD');

        $response = $this->withHeaders($this->octoHeaders())->postJson("/api/v1/octo/bookings/{$uuid}/cancel", []);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'CANCELLED');
        $response->assertJsonPath('uuid', $uuid);
        $response->assertJsonPath('cancellable', false);
    }

    public function test_cancel_confirmed_booking_returns_200_with_cancelled_status(): void
    {
        $affiliateId = $this->createAffiliate();
        // tour_booking_id = null so the code path that cancels a live tour booking is skipped
        $uuid        = $this->createOctoBooking($affiliateId, 'CONFIRMED');

        $response = $this->withHeaders($this->octoHeaders())->postJson("/api/v1/octo/bookings/{$uuid}/cancel", []);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'CANCELLED');
        $response->assertJsonPath('uuid', $uuid);
        $response->assertJsonPath('cancellable', false);
    }

    public function test_cancel_confirmed_booking_with_real_tour_booking_issues_refund_and_releases_seat(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $rateId      = $this->createRateForPackage($packageId, $affiliateId);
        // Slot is far in the future (~45 days) so the cancellation cutoff window is never in play.
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 10);

        $create = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', [
            'productId'      => (string) $packageId,
            'optionId'       => 'default',
            'availabilityId' => (string) $slot->id,
            'unitItems'      => [['unitId' => (string) $rateId]],
        ]);
        $uuid = $create->json('uuid');
        self::$allBookingUuids[] = $uuid;

        $confirm = $this->withHeaders($this->octoHeaders())->postJson("/api/v1/octo/bookings/{$uuid}/confirm", [
            'contact' => ['firstName' => 'Jane', 'lastName' => 'Doe', 'emailAddress' => 'jane@example.com'],
        ]);
        $confirm->assertStatus(200);
        $tourBookingId = $this->trackConfirmedTourBooking($uuid);

        $completedAmount = (float) DB::connection('tenant')->table('transactions')
            ->where('tour_booking_id', $tourBookingId)->where('status', 'Completed')->value('amount');
        $this->assertGreaterThan(0, $completedAmount, 'Confirm must have recorded a completed payment to refund against.');

        $cancel = $this->withHeaders($this->octoHeaders())->postJson("/api/v1/octo/bookings/{$uuid}/cancel", [
            'reason' => 'Guest requested cancellation',
        ]);

        $cancel->assertStatus(200);
        $cancel->assertJsonPath('status', 'CANCELLED');
        $cancel->assertJsonPath('cancellation.refund', 'FULL');

        $this->assertSame(
            'Cancelled',
            DB::connection('tenant')->table('tour_bookings_master')->where('id', $tourBookingId)->value('status')
        );

        $refundRow = DB::connection('tenant')->table('transactions')
            ->where('tour_booking_id', $tourBookingId)->where('status', 'Refund')->first();
        $this->assertNotNull($refundRow, 'Cancelling a CONFIRMED booking must create a Refund transaction row.');
        $this->assertEquals($completedAmount, (float) $refundRow->amount);

        $this->assertSame(1, DB::connection('tenant')->table('tour_booking_history')
            ->where('tour_booking_id', $tourBookingId)->where('operation', 'Cancelled')->count());

        // Seat released back: confirm incremented booked_tourists by 1, cancel should bring it back to 0.
        $summary = DB::connection('tenant')->table('tour_slot_summaries')->where('tour_slot_id', $slot->id)->first();
        if ($summary) {
            $this->assertSame(0, (int) $summary->booked_tourists);
        }
    }

    // ── PATCH /bookings/{uuid} — updateBooking ────────────────────────────────

    public function test_update_booking_returns_400_for_unknown_uuid(): void
    {
        $this->createAffiliate();

        $response = $this->withHeaders($this->octoHeaders())
            ->patchJson('/api/v1/octo/bookings/00000000-0000-0000-0000-000000000000', ['notes' => 'hello']);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'INVALID_BOOKING_UUID']);
    }

    public function test_update_booking_returns_409_for_cancelled_booking(): void
    {
        $affiliateId = $this->createAffiliate();
        $uuid        = $this->createOctoBooking($affiliateId, 'CANCELLED');

        $response = $this->withHeaders($this->octoHeaders())
            ->patchJson("/api/v1/octo/bookings/{$uuid}", ['notes' => 'update']);

        $response->assertStatus(409);
        $response->assertJson(['error' => 'BOOKING_ALREADY_CANCELLED']);
    }

    public function test_update_booking_returns_409_for_expired_booking(): void
    {
        $affiliateId = $this->createAffiliate();
        $uuid        = $this->createOctoBooking($affiliateId, 'ON_HOLD', [
            'expire_at' => Carbon::now()->subMinutes(10),
        ]);

        $response = $this->withHeaders($this->octoHeaders())
            ->patchJson("/api/v1/octo/bookings/{$uuid}", ['notes' => 'update']);

        $response->assertStatus(409);
        $response->assertJson(['error' => 'BOOKING_EXPIRED']);
    }

    public function test_update_booking_updates_notes_on_hold_booking(): void
    {
        $affiliateId = $this->createAffiliate();
        $uuid        = $this->createOctoBooking($affiliateId, 'ON_HOLD');

        $response = $this->withHeaders($this->octoHeaders())
            ->patchJson("/api/v1/octo/bookings/{$uuid}", ['notes' => 'Updated notes']);

        $response->assertStatus(200);
        $response->assertJsonPath('notes', 'Updated notes');
        $response->assertJsonPath('uuid', $uuid);
    }

    public function test_update_booking_extends_hold_expiration(): void
    {
        $affiliateId = $this->createAffiliate();
        $uuid        = $this->createOctoBooking($affiliateId, 'ON_HOLD');

        $before = Carbon::now()->addMinutes(25);

        $response = $this->withHeaders($this->octoHeaders())
            ->patchJson("/api/v1/octo/bookings/{$uuid}", ['expirationMinutes' => 60]);

        $response->assertStatus(200);

        $expiry = Carbon::parse($response->json('utcExpiresAt'));
        $this->assertTrue($expiry->gt($before), 'utcExpiresAt should be extended beyond original expiry');
    }

    public function test_update_booking_rejects_empty_unit_items(): void
    {
        $affiliateId = $this->createAffiliate();
        $uuid        = $this->createOctoBooking($affiliateId, 'ON_HOLD');

        $response = $this->withHeaders($this->octoHeaders())
            ->patchJson("/api/v1/octo/bookings/{$uuid}", ['unitItems' => []]);

        // OctoErrorResponse::unprocessableEntity returns HTTP 400 per OCTO spec
        $response->assertStatus(400);
        $response->assertJson(['error' => 'UNPROCESSABLE_ENTITY']);
    }

    public function test_update_booking_rejects_invalid_availability_id(): void
    {
        $affiliateId = $this->createAffiliate();
        // Booking has tour_slot_id=999999; changing to another unknown slot should fail
        $uuid        = $this->createOctoBooking($affiliateId, 'ON_HOLD');

        $response = $this->withHeaders($this->octoHeaders())
            ->patchJson("/api/v1/octo/bookings/{$uuid}", [
                'availabilityId' => '888888',
            ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'INVALID_AVAILABILITY_ID']);
    }

    // ── PATCH /bookings/{uuid} — updateBooking, CONFIRMED branch ──────────────
    // Uses createAndConfirmBooking() to get a real CONFIRMED booking (with a real
    // tour_bookings_master row) so the metadata-propagation, reschedule, and pax
    // add/delete reconciliation logic is exercised end-to-end. The pax tests fake
    // the queue since DetectPayoutAdjustment (dispatched by BokunService's history
    // helpers) would otherwise insert a real, unprocessed row into the `jobs` table.

    public function test_update_booking_confirmed_propagates_notes_and_reseller_reference_to_master(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $rateId      = $this->createRateForPackage($packageId, $affiliateId);
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 10);

        $uuid          = $this->createAndConfirmBooking($packageId, $rateId, $slot);
        $tourBookingId = DB::connection('tenant')->table('octo_bookings')->where('uuid', $uuid)->value('tour_booking_id');

        $response = $this->withHeaders($this->octoHeaders())->patchJson("/api/v1/octo/bookings/{$uuid}", [
            'notes'             => 'Please arrange wheelchair access',
            'resellerReference' => 'RESELLER-XYZ-999',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('notes', 'Please arrange wheelchair access');

        $masterRow = DB::connection('tenant')->table('tour_bookings_master')->where('id', $tourBookingId)->first();
        $this->assertSame('Please arrange wheelchair access', $masterRow->note);
        $this->assertStringContainsString('RESELLER-XYZ-999', $masterRow->ota_reference_booking);

        $this->assertSame(1, DB::connection('tenant')->table('tour_booking_history')
            ->where('tour_booking_id', $tourBookingId)->where('operation', 'Comments')->count());
    }

    public function test_update_booking_confirmed_reschedule_transfers_seats_and_creates_history(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $rateId      = $this->createRateForPackage($packageId, $affiliateId);
        $oldSlot     = $this->createBookableSlot($packageId, $affiliateId, 10);
        $newSlot     = $this->createBookableSlot($packageId, $affiliateId, 10, ['time_from' => '14:00:00', 'time_to' => '16:00:00']);

        $uuid          = $this->createAndConfirmBooking($packageId, $rateId, $oldSlot);
        $tourBookingId = DB::connection('tenant')->table('octo_bookings')->where('uuid', $uuid)->value('tour_booking_id');

        $response = $this->withHeaders($this->octoHeaders())
            ->patchJson("/api/v1/octo/bookings/{$uuid}", ['availabilityId' => (string) $newSlot->id]);

        $response->assertStatus(200);
        $response->assertJsonPath('availabilityId', (string) $newSlot->id);

        $this->assertSame($newSlot->id, (int) DB::connection('tenant')->table('octo_bookings')->where('uuid', $uuid)->value('tour_slot_id'));
        $this->assertSame($newSlot->id, (int) DB::connection('tenant')->table('tour_bookings_master')->where('id', $tourBookingId)->value('tour_slot_id'));

        $this->assertSame(1, DB::connection('tenant')->table('tour_booking_history')
            ->where('tour_booking_id', $tourBookingId)->where('operation', 'Rescheduled')->count());

        // Seat transfer: old slot summary released, new slot summary claimed.
        $oldSummary = DB::connection('tenant')->table('tour_slot_summaries')->where('tour_slot_id', $oldSlot->id)->first();
        $newSummary = DB::connection('tenant')->table('tour_slot_summaries')->where('tour_slot_id', $newSlot->id)->first();
        $this->assertSame(0, (int) $oldSummary->booked_tourists);
        $this->assertSame(1, (int) $newSummary->booked_tourists);
    }

    public function test_update_booking_confirmed_adds_pax_creates_net_payment(): void
    {
        Queue::fake();

        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $rateId      = $this->createRateForPackage($packageId, $affiliateId);
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 10);

        $uuid          = $this->createAndConfirmBooking($packageId, $rateId, $slot, null, 1);
        $tourBookingId = DB::connection('tenant')->table('octo_bookings')->where('uuid', $uuid)->value('tour_booking_id');
        $originalTotal = (float) DB::connection('tenant')->table('tour_bookings_master')->where('id', $tourBookingId)->value('total');

        $response = $this->withHeaders($this->octoHeaders())->patchJson("/api/v1/octo/bookings/{$uuid}", [
            'unitItems' => [
                ['unitId' => (string) $rateId],
                ['unitId' => (string) $rateId],
            ],
        ]);

        $response->assertStatus(200);

        $masterRow = DB::connection('tenant')->table('tour_bookings_master')->where('id', $tourBookingId)->first();
        $this->assertSame(2, (int) $masterRow->tourists);
        $this->assertGreaterThan($originalTotal, (float) $masterRow->total);

        // Original confirm transaction + the new net-payment transaction.
        $this->assertSame(2, DB::connection('tenant')->table('transactions')
            ->where('tour_booking_id', $tourBookingId)->where('status', 'Completed')->count());
        // One 'Payment' row from the original confirm, plus one from this pax addition.
        $this->assertSame(2, DB::connection('tenant')->table('tour_booking_history')
            ->where('tour_booking_id', $tourBookingId)->where('operation', 'Payment')->count());

        $summary = DB::connection('tenant')->table('tour_slot_summaries')->where('tour_slot_id', $slot->id)->first();
        $this->assertSame(2, (int) $summary->booked_tourists);
    }

    public function test_update_booking_confirmed_removes_pax_creates_refund(): void
    {
        Queue::fake();

        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $rateId      = $this->createRateForPackage($packageId, $affiliateId);
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 10);

        $uuid          = $this->createAndConfirmBooking($packageId, $rateId, $slot, null, 2);
        $tourBookingId = DB::connection('tenant')->table('octo_bookings')->where('uuid', $uuid)->value('tour_booking_id');
        $originalTotal = (float) DB::connection('tenant')->table('tour_bookings_master')->where('id', $tourBookingId)->value('total');

        $response = $this->withHeaders($this->octoHeaders())->patchJson("/api/v1/octo/bookings/{$uuid}", [
            'unitItems' => [['unitId' => (string) $rateId]],
        ]);

        $response->assertStatus(200);

        $masterRow = DB::connection('tenant')->table('tour_bookings_master')->where('id', $tourBookingId)->first();
        $this->assertSame(1, (int) $masterRow->tourists);
        $this->assertLessThan($originalTotal, (float) $masterRow->total);

        $refundRow = DB::connection('tenant')->table('transactions')
            ->where('tour_booking_id', $tourBookingId)->where('status', 'Refund')->first();
        $this->assertNotNull($refundRow, 'Removing pax from a CONFIRMED booking must create a Refund transaction.');
        $this->assertGreaterThan(0, (float) $refundRow->amount);

        $this->assertSame(1, DB::connection('tenant')->table('tour_booking_history')
            ->where('tour_booking_id', $tourBookingId)->where('operation', 'Refund')->count());

        $summary = DB::connection('tenant')->table('tour_slot_summaries')->where('tour_slot_id', $slot->id)->first();
        $this->assertSame(1, (int) $summary->booked_tourists);
    }

    // ── POST /bookings/{uuid}/extend — extendBooking ──────────────────────────

    public function test_extend_booking_returns_400_for_unknown_uuid(): void
    {
        $this->createAffiliate();

        $response = $this->withHeaders($this->octoHeaders())
            ->postJson('/api/v1/octo/bookings/00000000-0000-0000-0000-000000000000/extend', [
                'expirationMinutes' => 30,
            ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'INVALID_BOOKING_UUID']);
    }

    public function test_extend_booking_returns_400_for_confirmed_booking(): void
    {
        $affiliateId = $this->createAffiliate();
        $uuid        = $this->createOctoBooking($affiliateId, 'CONFIRMED');

        $response = $this->withHeaders($this->octoHeaders())
            ->postJson("/api/v1/octo/bookings/{$uuid}/extend", ['expirationMinutes' => 30]);

        // OctoErrorResponse::unprocessableEntity returns HTTP 400 per OCTO spec
        $response->assertStatus(400);
        $response->assertJson(['error' => 'UNPROCESSABLE_ENTITY']);
    }

    public function test_extend_booking_returns_409_for_cancelled_booking(): void
    {
        $affiliateId = $this->createAffiliate();
        $uuid        = $this->createOctoBooking($affiliateId, 'CANCELLED');

        $response = $this->withHeaders($this->octoHeaders())
            ->postJson("/api/v1/octo/bookings/{$uuid}/extend", ['expirationMinutes' => 30]);

        $response->assertStatus(409);
        $response->assertJson(['error' => 'BOOKING_ALREADY_CANCELLED']);
    }

    public function test_extend_booking_returns_409_for_expired_booking(): void
    {
        $affiliateId = $this->createAffiliate();
        $uuid        = $this->createOctoBooking($affiliateId, 'ON_HOLD', [
            'expire_at' => Carbon::now()->subMinutes(5),
        ]);

        $response = $this->withHeaders($this->octoHeaders())
            ->postJson("/api/v1/octo/bookings/{$uuid}/extend", ['expirationMinutes' => 30]);

        $response->assertStatus(409);
        $response->assertJson(['error' => 'BOOKING_EXPIRED']);
    }

    public function test_extend_booking_updates_expiry_on_hold_booking(): void
    {
        $affiliateId = $this->createAffiliate();
        $uuid        = $this->createOctoBooking($affiliateId, 'ON_HOLD');

        $response = $this->withHeaders($this->octoHeaders())
            ->postJson("/api/v1/octo/bookings/{$uuid}/extend", ['expirationMinutes' => 45]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'ON_HOLD');

        $expiry = Carbon::parse($response->json('utcExpiresAt'));
        $this->assertTrue($expiry->gt(Carbon::now()->addMinutes(40)), 'Expiry should be ~45 minutes from now');
    }

    // ── cancellable field ─────────────────────────────────────────────────────

    public function test_get_booking_on_hold_returns_cancellable_true(): void
    {
        $affiliateId = $this->createAffiliate();
        $uuid        = $this->createOctoBooking($affiliateId, 'ON_HOLD');

        $response = $this->withHeaders($this->octoHeaders())
            ->getJson("/api/v1/octo/bookings/{$uuid}");

        $response->assertStatus(200);
        $response->assertJsonPath('cancellable', true);
    }

    public function test_get_booking_cancelled_returns_cancellable_false(): void
    {
        $affiliateId = $this->createAffiliate();
        $uuid        = $this->createOctoBooking($affiliateId, 'CANCELLED');

        $response = $this->withHeaders($this->octoHeaders())
            ->getJson("/api/v1/octo/bookings/{$uuid}");

        $response->assertStatus(200);
        $response->assertJsonPath('cancellable', false);
    }

    // ── pricing capability on GET /products/{id} ──────────────────────────────

    public function test_pricing_capability_adds_pricing_from_to_units(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $this->createRateForPackage($packageId, $affiliateId);

        $response = $this->withHeaders($this->octoHeaders('pricing'))
            ->getJson('/api/v1/octo/products/' . $packageId);

        $response->assertStatus(200);

        $units = $response->json('options.0.units');
        $this->assertNotEmpty($units, 'Expected at least one unit');
        $this->assertArrayHasKey('pricingFrom', $units[0]);
        $this->assertNotEmpty($units[0]['pricingFrom']);

        $pricing = $units[0]['pricingFrom'][0];
        $this->assertArrayHasKey('original', $pricing);
        $this->assertArrayHasKey('retail', $pricing);
        $this->assertArrayHasKey('currency', $pricing);
        $this->assertArrayHasKey('currencyPrecision', $pricing);
    }

    public function test_no_pricing_capability_omits_pricing_from_units(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $this->createRateForPackage($packageId, $affiliateId);

        $response = $this->withHeaders($this->octoHeaders())
            ->getJson('/api/v1/octo/products/' . $packageId);

        $response->assertStatus(200);

        $units = $response->json('options.0.units');
        if (!empty($units)) {
            $this->assertArrayNotHasKey('pricingFrom', $units[0]);
        }
    }

    // ── OctoService::resolveRemainingSeats — seat-locking formula ─────────────
    // Direct calls to the static method (not via HTTP) to validate the core anti-overbooking
    // accounting in isolation. Each test issues at least one HTTP call first (via the OCTO
    // endpoints) so the default DB connection is switched to 'tenant' by OctoAuthentication,
    // matching how this method is actually invoked in production (inside createBooking's
    // lockForUpdate transaction).

    public function test_resolve_remaining_seats_subtracts_confirmed_bookings_from_affiliate_limit(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $rateId      = $this->createRateForPackage($packageId, $affiliateId);
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 5);

        $create = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', [
            'productId'      => (string) $packageId,
            'optionId'       => 'default',
            'availabilityId' => (string) $slot->id,
            'unitItems'      => [['unitId' => (string) $rateId], ['unitId' => (string) $rateId]],
        ]);
        $uuid = $create->json('uuid');
        self::$allBookingUuids[] = $uuid;

        $confirm = $this->withHeaders($this->octoHeaders())->postJson("/api/v1/octo/bookings/{$uuid}/confirm", [
            'contact' => ['firstName' => 'Jane', 'lastName' => 'Doe', 'emailAddress' => 'jane@example.com'],
        ]);
        $confirm->assertStatus(200);
        $this->trackConfirmedTourBooking($uuid);

        $remaining = OctoService::resolveRemainingSeats($slot->id, $affiliateId, 5, 100, $packageId);

        $this->assertSame(3, $remaining, 'A 5-seat affiliate allocation minus 2 confirmed tourists should leave 3 remaining.');
    }

    public function test_resolve_remaining_seats_subtracts_active_on_hold_reservations(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        $rateId      = $this->createRateForPackage($packageId, $affiliateId);
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 5);

        $create = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', [
            'productId'      => (string) $packageId,
            'optionId'       => 'default',
            'availabilityId' => (string) $slot->id,
            'unitItems'      => [['unitId' => (string) $rateId], ['unitId' => (string) $rateId]],
        ]);
        $create->assertStatus(200);
        self::$allBookingUuids[] = $create->json('uuid');
        // Left ON_HOLD — not confirmed.

        $remaining = OctoService::resolveRemainingSeats($slot->id, $affiliateId, 5, 100, $packageId);

        $this->assertSame(3, $remaining, 'An active ON_HOLD hold for 2 tourists must reduce remaining seats just like a confirmed booking.');
    }

    public function test_resolve_remaining_seats_caps_capacity_at_lower_of_slot_and_affiliate_limit(): void
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackageForAffiliate($affiliateId);
        // slot-wide total = 3, affiliate limit = 10 — the lower slot-wide cap must win.
        $slot        = $this->createBookableSlot($packageId, $affiliateId, 10, ['time_slot_seats' => 3]);

        // Establish the tenant DB connection (no other HTTP call happens in this test).
        $this->withHeaders($this->octoHeaders())->getJson('/api/v1/octo/supplier')->assertStatus(200);

        $remaining = OctoService::resolveRemainingSeats($slot->id, $affiliateId, 10, 3, $packageId);

        $this->assertSame(3, $remaining, 'Remaining seats must not exceed the slot-wide seat total even when the affiliate limit is higher.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createRateForPackage(int $packageId, int $affiliateId, array $overrides = []): int
    {
        $id = DB::connection('tenant')->table('tour_package_rates')->insertGetId(array_merge([
            'tour_package_id'              => $packageId,
            'affiliate_id'                 => $affiliateId,
            'rate_for'                     => 'Adult',
            'age_from'                     => 18,
            'age_to'                       => 99,
            'rate'                         => 100.00,
            'permit_fee'                   => 0,
            'additional_charge'            => 0,
            'processing_charge_percentage' => '0',
            'processing_charge'            => 0,
            'additional_tax_percentage'    => 0,
            'margin'                       => 0,
            'scheduling_year'              => (int) date('Y'),
            'status'                       => 'Active',
            'orderby'                      => 1,
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

    /**
     * Creates a real, bookable tour_slot_times row plus its affiliate seat allocation
     * (tour_slot_times_affiliate + affiliate_slot_times), so createBooking/confirmBooking/
     * cancelBooking success paths have a genuine slot to operate on instead of the fake
     * tour_slot_id=999999 used by createOctoBooking(). Pass $seatLimit = null to omit the
     * affiliate_slot_times row entirely (simulates "no seat allocation configured").
     *
     * All rows are local-DB-only fixtures tracked in cleanup*Ids and removed in tearDown().
     */
    private function createBookableSlot(int $packageId, int $affiliateId, ?int $seatLimit = 10, array $overrides = []): object
    {
        $timeFrom       = $overrides['time_from'] ?? '10:00:00';
        $timeTo         = $overrides['time_to'] ?? '12:00:00';
        $date           = $overrides['date'] ?? Carbon::now()->addDays(45)->format('Y-m-d');
        $bookableStatus = $overrides['bookable_status'] ?? 'Open';
        $slotSeats      = $overrides['time_slot_seats'] ?? 100;

        // tour_slot_times.tour_slot_id is a FK to tour_slot_dates, so the parent
        // date row has to exist before the time row can be inserted.
        $slotDateId = DB::connection('tenant')->table('tour_slot_dates')->insertGetId([
            'tour_package_id' => $packageId,
            'date'            => $date,
            'seats'           => $slotSeats,
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
            'time_slot_seats' => $slotSeats,
            'bookable_status' => $bookableStatus,
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

        if ($seatLimit !== null) {
            $astId = DB::connection('tenant')->table('affiliate_slot_times')->insertGetId([
                'tour_slot_time_id' => $tstaId,
                'affiliate_id'      => $affiliateId,
                'seat_limit'        => $seatLimit,
                'deleted_by'        => 0,
                'created_at'        => Carbon::now(),
                'updated_at'        => Carbon::now(),
            ]);
            $this->cleanupAstIds[] = $astId;
        }

        return (object) ['id' => $slotId, 'date' => $date, 'time_from' => $timeFrom, 'time_to' => $timeTo];
    }

    /** Fetches the tour_booking_id an octo_booking was confirmed against, and tracks it for cleanup. */
    private function trackConfirmedTourBooking(string $uuid): ?int
    {
        $tourBookingId = DB::connection('tenant')->table('octo_bookings')->where('uuid', $uuid)->value('tour_booking_id');
        if ($tourBookingId) {
            $this->cleanupTourBookingIds[] = $tourBookingId;
        }
        return $tourBookingId;
    }

    /**
     * Creates an ON_HOLD booking (via the real endpoint) and immediately confirms it,
     * returning the booking uuid. Tracks the uuid and resulting tour_booking_id for cleanup.
     */
    private function createAndConfirmBooking(int $packageId, int $rateId, object $slot, ?string $resellerReference = null, int $unitCount = 1): string
    {
        $create = $this->withHeaders($this->octoHeaders())->postJson('/api/v1/octo/bookings', [
            'productId'      => (string) $packageId,
            'optionId'       => 'default',
            'availabilityId' => (string) $slot->id,
            'unitItems'      => array_fill(0, $unitCount, ['unitId' => (string) $rateId]),
        ]);
        $create->assertStatus(200);
        $uuid = $create->json('uuid');
        self::$allBookingUuids[] = $uuid;

        $payload = ['contact' => ['firstName' => 'Jane', 'lastName' => 'Doe', 'emailAddress' => 'jane@example.com']];
        if ($resellerReference !== null) {
            $payload['resellerReference'] = $resellerReference;
        }

        $confirm = $this->withHeaders($this->octoHeaders())->postJson("/api/v1/octo/bookings/{$uuid}/confirm", $payload);
        $confirm->assertStatus(200);
        $this->trackConfirmedTourBooking($uuid);

        return $uuid;
    }

    private function createOctoBooking(int $affiliateId, string $status = 'ON_HOLD', array $overrides = []): string
    {
        $uuid = (string) \Illuminate\Support\Str::uuid();

        DB::connection('tenant')->table('octo_bookings')->insert(array_merge([
            'uuid'            => $uuid,
            'affiliate_id'    => $affiliateId,
            'tour_slot_id'    => 999999,
            'tour_package_id' => 0,
            'tour_booking_id' => null,
            'status'          => $status,
            'tourists'        => 1,
            'infants'         => 0,
            'unit_items'      => json_encode([['uuid' => (string) \Illuminate\Support\Str::uuid(), 'unitId' => '1', 'supplierReference' => 'TEST01', 'resellerReference' => '']]),
            'contact'         => null,
            'notes'           => null,
            'expire_at'       => Carbon::now()->addMinutes(30),
            'confirmed_at'    => $status === 'CONFIRMED' ? Carbon::now() : null,
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now(),
        ], $overrides));

        self::$allBookingUuids[] = $uuid;
        return $uuid;
    }
}
