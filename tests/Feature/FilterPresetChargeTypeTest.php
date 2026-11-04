<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\FilterPreset;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Confirms "Save Preset" on the Payment Breakdown Report round-trips the new
 * Charge Type filter through the real HTTP + DB layers, not just the JS/repo:
 * FilterPresetController::store() -> FilterPreset (array-cast 'filters' JSON
 * column, no field whitelisting) -> FilterPresetController::show().
 */
class FilterPresetChargeTypeTest extends TestCase
{
    protected $admin;
    protected array $createdAdminIds = [];
    protected array $createdPresetIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'manage-payment-breakdown-filters', 'guard_name' => 'admin']);

        $this->admin = Admin::factory()->create();
        $this->createdAdminIds[] = $this->admin->id;
        $this->admin->givePermissionTo(['manage-payment-breakdown-filters']);

        $this->withoutMiddleware([
            \App\Http\Middleware\VerifyCsrfToken::class,
        ]);
    }

    protected function tearDown(): void
    {
        if (!empty($this->createdPresetIds)) {
            FilterPreset::whereIn('id', $this->createdPresetIds)->delete();
        }
        if (!empty($this->createdAdminIds)) {
            DB::table('admins')->whereIn('id', $this->createdAdminIds)->delete();
        }
        parent::tearDown();
    }

    public function test_save_preset_round_trips_charge_types_filter(): void
    {
        $payload = [
            'name' => 'Charge Type Test Preset ' . uniqid(),
            'report_type' => 'payment_breakdown',
            'filters' => [
                'date_from' => now()->toDateString(),
                'date_to' => now()->toDateString(),
                'charge_types' => ['tours', 'custom'],
            ],
        ];

        $storeResponse = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.tour-operator.report.filter-presets.store'), $payload);

        $storeResponse->assertStatus(201);
        $storeResponse->assertJsonPath('data.filters.charge_types', ['tours', 'custom']);

        $presetId = $storeResponse->json('data.id');
        $this->assertNotNull($presetId);
        $this->createdPresetIds[] = $presetId;

        // Fetch back independently (simulates "Load Preset") to prove it
        // wasn't just echoed from the request but actually persisted.
        $showResponse = $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.tour-operator.report.filter-presets.show', ['id' => $presetId]));

        $showResponse->assertStatus(200);
        $showResponse->assertJsonPath('data.filters.charge_types', ['tours', 'custom']);
    }

    public function test_save_preset_rejects_invalid_charge_type_value(): void
    {
        $payload = [
            'name' => 'Invalid Charge Type Preset ' . uniqid(),
            'report_type' => 'payment_breakdown',
            'filters' => [
                'charge_types' => ['not-a-real-bucket'],
            ],
        ];

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.tour-operator.report.filter-presets.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['filters.charge_types.0']);
    }
}
