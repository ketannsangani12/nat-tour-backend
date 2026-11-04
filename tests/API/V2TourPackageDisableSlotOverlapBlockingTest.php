<?php

namespace Tests\API;

use App\Http\Controllers\Common\TourPackageController;
use App\Models\Admin;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\TestCase;

class V2TourPackageDisableSlotOverlapBlockingTest extends TestCase
{
    protected static array $createdPackageIds = [];

    protected array $tenantMiddleware = [
        InitializeTenancyByDomain::class,
        PreventAccessFromCentralDomains::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware($this->tenantMiddleware);
        $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'test.localhost';
    }

    protected function tearDown(): void
    {
        if (!empty(static::$createdPackageIds)) {
            DB::table('tour_packages')->whereIn('id', static::$createdPackageIds)->delete();
            static::$createdPackageIds = [];
        }
        parent::tearDown();
    }

    protected function insertTestPackage(array $overrides = []): int
    {
        $now = Carbon::now();
        $operatorId = DB::table('tour_operators')->value('id');
        $id = DB::table('tour_packages')->insertGetId([
            'tour_operator_id'              => $operatorId,
            'name'                          => 'DisableOverlapTest ' . uniqid(),
            'short_description'             => '',
            'long_description'              => '',
            'duration'                      => 1,
            'hours'                         => 1,
            'minutes'                       => 0,
            'day_hours'                     => 1,
            'things_to_bring'               => '[]',
            'important_notes'               => '',
            'status'                        => 'Active',
            'type'                          => 1,
            'service_commission_percentage' => 0,
            'stripe_commission_percentage'  => 0,
            'frontend_enabled'              => 1,
            'package_has_slots'             => 1,
            'package_has_waiver'            => 0,
            'package_has_permit'            => 0,
            'is_combo_package'              => 0,
            'bundle_package'                => 0,
            'overbook_seats'                => 0,
            'is_group_rate_enabled'         => 0,
            'deleted_by'                    => 0,
            'disable_slot_overlap_blocking' => 0,
            'created_by'                    => 1,
            'created_by_user_type'          => 'Admin',
            'created_at'                    => $now,
            'updated_at'                    => $now,
            ...$overrides,
        ]);

        static::$createdPackageIds[] = $id;
        return $id;
    }

    protected function packageUrl(int $packageId): string
    {
        $operatorId = DB::table('tour_operators')->value('id');
        $date = now()->format('Y-m-d');
        return "/api/v2/tour-package/{$date}/{$operatorId}/{$packageId}";
    }

    protected function controller(): TourPackageController
    {
        return app(TourPackageController::class);
    }

    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        session(['userType' => 'Admin']);
        return $admin;
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * The V2 API includes disable_slot_overlap_blocking = 0 in tourPackageData[0].
     */
    public function testApiReturnsFlagAsZeroWhenDisabled(): void
    {
        $packageId = $this->insertTestPackage(['disable_slot_overlap_blocking' => 0]);

        $response = $this->getJson($this->packageUrl($packageId));

        $this->assertEquals(200, $response->status(),
            'V2 tour-package endpoint failed: ' . $response->getContent()
        );

        $response->assertJsonStructure(['tourPackageData']);

        $pkg = collect($response->json('tourPackageData'))->firstWhere('package_id', $packageId);
        $this->assertNotNull($pkg, "tourPackageData should contain package {$packageId}");
        $this->assertArrayHasKey('disable_slot_overlap_blocking', $pkg);
        $this->assertEquals(0, $pkg['disable_slot_overlap_blocking']);
    }

    /**
     * The V2 API returns disable_slot_overlap_blocking = 1 when set on the package.
     */
    public function testApiReturnsFlagAsOneWhenEnabled(): void
    {
        $packageId = $this->insertTestPackage(['disable_slot_overlap_blocking' => 1]);

        $response = $this->getJson($this->packageUrl($packageId));

        $this->assertEquals(200, $response->status(),
            'V2 tour-package endpoint failed: ' . $response->getContent()
        );

        $pkg = collect($response->json('tourPackageData'))->firstWhere('package_id', $packageId);
        $this->assertNotNull($pkg, "tourPackageData should contain package {$packageId}");
        $this->assertEquals(1, $pkg['disable_slot_overlap_blocking']);
    }

    /**
     * updateSetting() persists disable_slot_overlap_blocking = 1 to the DB.
     */
    public function testUpdateSettingEnablesFlag(): void
    {
        $this->authAsAdmin();
        $packageId = $this->insertTestPackage(['disable_slot_overlap_blocking' => 0]);

        $request = new Request();
        $request->merge(['disable_slot_overlap_blocking' => '1']);

        $response = $this->controller()->updateSetting($request, $packageId);
        $this->assertEquals(200, $response->getStatusCode());

        $value = DB::table('tour_packages')->where('id', $packageId)->value('disable_slot_overlap_blocking');
        $this->assertEquals(1, (int) $value);
    }

    /**
     * updateSetting() persists disable_slot_overlap_blocking = 0 to the DB.
     */
    public function testUpdateSettingDisablesFlag(): void
    {
        $this->authAsAdmin();
        $packageId = $this->insertTestPackage(['disable_slot_overlap_blocking' => 1]);

        $request = new Request();
        $request->merge(['disable_slot_overlap_blocking' => '0']);

        $response = $this->controller()->updateSetting($request, $packageId);
        $this->assertEquals(200, $response->getStatusCode());

        $value = DB::table('tour_packages')->where('id', $packageId)->value('disable_slot_overlap_blocking');
        $this->assertEquals(0, (int) $value);
    }

    /**
     * Toggling the flag via updateSetting() writes an audit record to records_history.
     */
    public function testUpdateSettingWritesAuditRecord(): void
    {
        $this->authAsAdmin();
        $packageId = $this->insertTestPackage(['disable_slot_overlap_blocking' => 0]);

        $request = new Request();
        $request->merge(['disable_slot_overlap_blocking' => '1']);

        $this->controller()->updateSetting($request, $packageId);

        $history = DB::table('records_history')
            ->where('record_id_1', $packageId)
            ->where('table_name', 'tour_packages')
            ->where('operation_type', 'u')
            ->latest('operation_at')
            ->first();

        $this->assertNotNull($history, 'An audit record should be written when the setting changes');

        $newValues = json_decode($history->new_values, true);
        $this->assertArrayHasKey('disable_slot_overlap_blocking', $newValues);
        $this->assertEquals(1, (int) $newValues['disable_slot_overlap_blocking']);
    }

    /**
     * After enabling the flag via updateSetting(), a fresh V2 API call returns 1.
     */
    public function testApiReflectsValueAfterUpdateSetting(): void
    {
        $this->authAsAdmin();
        $packageId = $this->insertTestPackage(['disable_slot_overlap_blocking' => 0]);

        $request = new Request();
        $request->merge(['disable_slot_overlap_blocking' => '1']);
        $this->controller()->updateSetting($request, $packageId);

        $response = $this->getJson($this->packageUrl($packageId));

        $this->assertEquals(200, $response->status(),
            'V2 tour-package endpoint failed: ' . $response->getContent()
        );

        $pkg = collect($response->json('tourPackageData'))->firstWhere('package_id', $packageId);
        $this->assertNotNull($pkg, "tourPackageData should contain package {$packageId}");
        $this->assertEquals(1, $pkg['disable_slot_overlap_blocking']);
    }
}
