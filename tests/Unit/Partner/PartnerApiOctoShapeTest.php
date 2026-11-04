<?php

namespace Tests\Unit\Partner;

use App\Repositories\PartnerAPI\PartnerApiRepository;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Verifies the octo-shaped Partner endpoints delegate to the reused OCTO
 * implementation, resolve the partner affiliate, honour capabilities, and
 * expose cross-tenant packages — all without any HTTP-level tenancy plumbing (the repo
 * methods are called directly with a container-bound Request).
 *
 * Run with:
 *   docker exec -it nat-app php artisan test tests/Unit/Partner/PartnerApiOctoShapeTest.php
 */
class PartnerApiOctoShapeTest extends TestCase
{
    private const RESELLER = 'partner-test-reseller';
    private const API_KEY   = 'partner-test-key';

    protected array $cleanupCustomerIds = [];
    protected array $cleanupPackageIds  = [];
    protected array $cleanupRateIds     = [];
    protected array $cleanupSlotIds     = [];
    protected array $cleanupSlotDateIds = [];
    protected array $cleanupTstaIds     = [];
    protected array $cleanupAstIds      = [];
    protected array $cleanupUuids       = [];

    protected function tearDown(): void
    {
        if (!empty($this->cleanupUuids)) {
            DB::connection('tenant')->table('octo_bookings')->whereIn('uuid', $this->cleanupUuids)->delete();
        }
        if (!empty($this->cleanupAstIds)) {
            DB::connection('tenant')->table('affiliate_slot_times')->whereIn('id', $this->cleanupAstIds)->delete();
        }
        if (!empty($this->cleanupTstaIds)) {
            DB::connection('tenant')->table('tour_slot_times_affiliate')->whereIn('id', $this->cleanupTstaIds)->delete();
        }
        if (!empty($this->cleanupSlotIds)) {
            DB::connection('tenant')->table('tour_slot_summaries')->whereIn('tour_slot_id', $this->cleanupSlotIds)->delete();
            DB::connection('tenant')->table('tour_slot_times')->whereIn('id', $this->cleanupSlotIds)->delete();
        }
        if (!empty($this->cleanupSlotDateIds)) {
            DB::connection('tenant')->table('tour_slot_dates')->whereIn('id', $this->cleanupSlotDateIds)->delete();
        }
        if (!empty($this->cleanupRateIds)) {
            DB::connection('tenant')->table('tour_package_rates')->whereIn('id', $this->cleanupRateIds)->delete();
        }
        if (!empty($this->cleanupPackageIds)) {
            DB::connection('tenant')->table('partner_cross_tenant_origins')->whereIn('package_id', $this->cleanupPackageIds)->delete();
            DB::connection('tenant')->table('package_affiliate_assignments')->whereIn('package_id', $this->cleanupPackageIds)->delete();
            DB::connection('tenant')->table('tour_packages')->whereIn('id', $this->cleanupPackageIds)->delete();
        }
        if (!empty($this->cleanupCustomerIds)) {
            DB::connection('tenant')->table('customers')->whereIn('id', $this->cleanupCustomerIds)->delete();
        }
        parent::tearDown();
    }

    private function createAffiliate(array $overrides = []): int
    {
        $operatorId = DB::connection('tenant')->table('tour_operators')->value('id');
        $id = DB::connection('tenant')->table('customers')->insertGetId(array_merge([
            'tour_operator_id' => $operatorId,
            'name'             => self::RESELLER,
            'email'            => 'partner-' . uniqid() . '@example.com',
            'phone_number'     => '0000000000',
            'is_affiliate'     => 'Yes',
            'is_ota'           => 1,
            // Unique per affiliate: OCTO resolves the reseller by ota_id, so two fixtures
            // sharing one would silently collapse into the same affiliate.
            'ota_id'           => self::RESELLER . '-' . uniqid(),
            'reseller_api_key' => self::API_KEY . '-' . uniqid(),
            'status'           => 'Active',
            'created_at'       => Carbon::now(),
            'updated_at'       => Carbon::now(),
        ], $overrides));
        $this->cleanupCustomerIds[] = $id;
        return $id;
    }

    private function createPackage(int $affiliateId, array $overrides = []): int
    {
        $operatorId = DB::connection('tenant')->table('tour_operators')->value('id');
        $packageId = DB::connection('tenant')->table('tour_packages')->insertGetId(array_merge([
            'tour_operator_id'     => $operatorId,
            'name'                 => 'Partner Shape Test ' . uniqid(),
            'status'               => 'Active',
            'type'                 => 'Regular',
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => Carbon::now(),
            'updated_at'           => Carbon::now(),
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

    private function bindRequest(int $affiliateId, string $capabilities = 'pricing'): Request
    {
        $request = Request::create('/api/v1/partner-api/supplier', 'GET');
        $request->headers->set('Octo-Capabilities', $capabilities);
        $request['data'] = ['AffiliateId' => $affiliateId];
        $this->app->instance('request', $request);
        return $request;
    }

    public function test_get_supplier_delegates_and_rewrites_endpoint_to_partner_path(): void
    {
        $affiliateId = $this->createAffiliate();
        $request     = $this->bindRequest($affiliateId);

        $response = app(PartnerApiRepository::class)->getSupplier($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertArrayHasKey('endpoint', $data);
        $this->assertStringEndsWith('/api/v1/partner-api', $data['endpoint']);
        $this->assertArrayHasKey('contact', $data);
    }

    public function test_setup_rejects_non_ota_affiliate(): void
    {
        $affiliateId = $this->createAffiliate(['is_ota' => 0, 'ota_id' => null]);
        $request     = $this->bindRequest($affiliateId);

        $response = app(PartnerApiRepository::class)->getSupplier($request);

        // OctoErrorResponse::unprocessableEntity => HTTP 400
        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_missing_credentials_are_forbidden(): void
    {
        $request = Request::create('/api/v1/partner-api/supplier', 'GET');
        $this->app->instance('request', $request);

        $response = app(PartnerApiRepository::class)->getSupplier($request);

        // OctoErrorResponse::forbidden => HTTP 403
        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_product_list_includes_cross_tenant_package(): void
    {
        $affiliateId   = $this->createAffiliate();
        $crossTenantId = $this->createPackage($affiliateId);
        DB::connection('tenant')->table('partner_cross_tenant_origins')->insert([
            'package_id'        => $crossTenantId,
            'origin_package_id' => 999999,
            'origin_tenant'     => 'dixies',
            'time_from'         => '09:00:00',
            'created_at'        => Carbon::now(),
            'updated_at'        => Carbon::now(),
        ]);
        $request = $this->bindRequest($affiliateId);

        $response = app(PartnerApiRepository::class)->getProductList($request);

        $this->assertSame(200, $response->getStatusCode());
        $ids = collect($response->getData(true))->pluck('id')->all();
        $this->assertContains((string) $crossTenantId, $ids);
    }

    // ── Booking reservation + retrieval ──────────────────────────────────────────

    public function test_reservation_honours_a_client_supplied_uuid_and_replays_idempotently(): void
    {
        [$affiliateId, $payload] = $this->bookablePayload();
        $uuid = (string) Str::uuid();
        $payload['uuid'] = $uuid;

        $first = $this->reserve($affiliateId, $payload);
        $this->assertSame(200, $first->getStatusCode());
        $this->assertSame($uuid, $first->getData(true)['uuid'], 'The reseller-supplied uuid must be the booking uuid.');

        $replay = $this->reserve($affiliateId, $payload);
        $this->assertSame(200, $replay->getStatusCode());
        $this->assertSame($uuid, $replay->getData(true)['uuid']);
        $this->assertSame(
            1,
            DB::connection('tenant')->table('octo_bookings')->where('uuid', $uuid)->count(),
            'A replayed reservation must not reserve inventory twice.'
        );
    }

    public function test_reservation_rejects_a_replayed_uuid_carrying_different_details(): void
    {
        [$affiliateId, $payload, $rateId] = $this->bookablePayload();
        $uuid = (string) Str::uuid();
        $payload['uuid'] = $uuid;

        $this->assertSame(200, $this->reserve($affiliateId, $payload)->getStatusCode());

        $payload['unitItems'] = [['unitId' => (string) $rateId], ['unitId' => (string) $rateId]];
        $conflict = $this->reserve($affiliateId, $payload);

        $this->assertSame(409, $conflict->getStatusCode());
        $this->assertSame('BOOKING_UUID_CONFLICT', $conflict->getData(true)['error']);
    }

    public function test_get_booking_by_uuid_returns_the_booking(): void
    {
        [$affiliateId, $payload] = $this->bookablePayload();
        $uuid = (string) Str::uuid();
        $payload['uuid'] = $uuid;

        $this->assertSame(200, $this->reserve($affiliateId, $payload)->getStatusCode());

        $request = Request::create('/api/v1/partner-api/bookings/' . $uuid, 'GET');
        $request['data'] = ['AffiliateId' => $affiliateId];
        $this->app->instance('request', $request);

        $response = app(PartnerApiRepository::class)->getBookingByUuid($request, $uuid);

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertSame($uuid, $data['uuid']);
        $this->assertSame('ON_HOLD', $data['status']);
    }

    public function test_get_booking_by_uuid_does_not_expose_another_affiliates_booking(): void
    {
        [$affiliateId, $payload] = $this->bookablePayload();
        $uuid = (string) Str::uuid();
        $payload['uuid'] = $uuid;

        $this->assertSame(200, $this->reserve($affiliateId, $payload)->getStatusCode());

        $otherAffiliateId = $this->createAffiliate();
        $request = Request::create('/api/v1/partner-api/bookings/' . $uuid, 'GET');
        $request['data'] = ['AffiliateId' => $otherAffiliateId];
        $this->app->instance('request', $request);

        $response = app(PartnerApiRepository::class)->getBookingByUuid($request, $uuid);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('INVALID_BOOKING_UUID', $response->getData(true)['error']);
    }

    public function test_get_booking_list_filters_by_supplier_reference(): void
    {
        [$affiliateId, $payload] = $this->bookablePayload();
        $uuid = (string) Str::uuid();
        $payload['uuid'] = $uuid;

        $created = $this->reserve($affiliateId, $payload);
        $this->assertSame(200, $created->getStatusCode());
        $supplierReference = $created->getData(true)['supplierReference'];

        $request = Request::create('/api/v1/partner-api/bookings', 'GET', ['supplierReference' => $supplierReference]);
        $request['data'] = ['AffiliateId' => $affiliateId];
        $this->app->instance('request', $request);

        $response = app(PartnerApiRepository::class)->getBookingList($request);

        $this->assertSame(200, $response->getStatusCode());
        $uuids = collect($response->getData(true))->pluck('uuid')->all();
        $this->assertSame([$uuid], $uuids);
    }

    /**
     * Guards the regression the reseller reported: before GET /bookings/{uuid} was routed,
     * the request fell through to the api/v1 catch-all and came back as a generic
     * 400 general.bad_request, which reads as "bad UUID" rather than "no such endpoint".
     */
    public function test_get_booking_by_uuid_is_routed_rather_than_hitting_the_catch_all(): void
    {
        $response = $this->getJson('/api/v1/partner-api/bookings/' . Str::uuid());

        $response->assertStatus(401);
        $this->assertNotSame('general.bad_request', $response->json('message'));
    }

    public function test_get_booking_list_requires_a_filter(): void
    {
        $affiliateId = $this->createAffiliate();
        $request     = Request::create('/api/v1/partner-api/bookings', 'GET');
        $request['data'] = ['AffiliateId' => $affiliateId];
        $this->app->instance('request', $request);

        $response = app(PartnerApiRepository::class)->getBookingList($request);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('BAD_REQUEST', $response->getData(true)['error']);
    }

    /**
     * The OpenAPI document is hand-maintained and is handed to resellers to import into
     * Postman, so a documented endpoint that does not exist is a broken integration for
     * them rather than a broken build for us. Every documented partner path must resolve
     * to a real route with the documented verb.
     */
    public function test_documented_partner_endpoints_all_exist(): void
    {
        $doc = json_decode(file_get_contents(base_path('storage/api-docs/octo-api-docs.json')), true);
        $this->assertIsArray($doc, 'The OpenAPI document is not valid JSON.');

        $registered = [];
        foreach (Route::getRoutes() as $route) {
            foreach ($route->methods() as $method) {
                $registered[$method . ' ' . $route->uri()] = true;
            }
        }

        $documented = array_filter(array_keys($doc['paths']), fn ($path) => str_starts_with($path, '/partner-api'));
        $this->assertNotEmpty($documented, 'No partner endpoints are documented.');

        foreach ($documented as $path) {
            foreach (array_keys($doc['paths'][$path]) as $verb) {
                $key = strtoupper($verb) . ' api/v1' . $path;
                $this->assertArrayHasKey($key, $registered, "Documented endpoint has no route: {$key}");
            }
        }
    }

    // ── Booking fixtures ─────────────────────────────────────────────────────────

    /**
     * Builds an affiliate with a bookable package/rate/slot and returns
     * [affiliateId, reservation payload, rateId].
     */
    private function bookablePayload(): array
    {
        $affiliateId = $this->createAffiliate();
        $packageId   = $this->createPackage($affiliateId);
        $rateId      = $this->createRate($packageId, $affiliateId);
        $slotId      = $this->createBookableSlot($packageId, $affiliateId);

        return [$affiliateId, [
            'productId'      => (string) $packageId,
            'optionId'       => 'default',
            'availabilityId' => (string) $slotId,
            'unitItems'      => [['unitId' => (string) $rateId]],
        ], $rateId];
    }

    private function reserve(int $affiliateId, array $payload): JsonResponse
    {
        $request = Request::create('/api/v1/partner-api/bookings', 'POST', $payload);
        $request['data'] = ['AffiliateId' => $affiliateId];
        $this->app->instance('request', $request);

        $response = app(PartnerApiRepository::class)->bookingReservation($request);

        if (isset($payload['uuid'])) {
            $this->cleanupUuids[] = $payload['uuid'];
        }

        return $response;
    }

    private function createRate(int $packageId, int $affiliateId): int
    {
        $id = DB::connection('tenant')->table('tour_package_rates')->insertGetId([
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
        ]);
        $this->cleanupRateIds[] = $id;
        return $id;
    }

    private function createBookableSlot(int $packageId, int $affiliateId, int $seatLimit = 10): int
    {
        $date     = Carbon::now()->addDays(45)->format('Y-m-d');
        $timeFrom = '10:00:00';
        $timeTo   = '12:00:00';

        // tour_slot_times.tour_slot_id is a FK to tour_slot_dates.
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

        $astId = DB::connection('tenant')->table('affiliate_slot_times')->insertGetId([
            'tour_slot_time_id' => $tstaId,
            'affiliate_id'      => $affiliateId,
            'seat_limit'        => $seatLimit,
            'deleted_by'        => 0,
            'created_at'        => Carbon::now(),
            'updated_at'        => Carbon::now(),
        ]);
        $this->cleanupAstIds[] = $astId;

        return $slotId;
    }
}
