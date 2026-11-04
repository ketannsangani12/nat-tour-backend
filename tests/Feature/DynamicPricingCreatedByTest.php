<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\SurchargeDate;
use App\Repositories\TourPackageRate\TourPackageRateRepository;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * All three Dynamic Pricing tabs (package rates, custom date rates, global
 * rates) build their filters from TourPackageRateRepository::getDataForFilter(),
 * which stamps a created_by_with_name onto every surcharge date it loads.
 *
 * getCreatedByUserName() used to read ->name straight off Admin::find(), and
 * Admin soft deletes, so once a creator's account was removed find() returned
 * null and reading ->name raised an ErrorException. The three page actions
 * caught it and returned back(), so the tabs silently bounced to the dashboard
 * and read like a permissions guard rather than a broken lookup. On the kens
 * tenant five surcharge dates were created by an admin deleted 2026-07-27.
 */
class DynamicPricingCreatedByTest extends TestCase
{
    public function test_returns_dash_when_the_creator_record_is_gone()
    {
        $row = new SurchargeDate();
        $row->created_by = 999999999;
        $row->created_by_user_type = 'Admin';

        $this->assertSame('-', $this->repository()->getCreatedByUserName($row));
    }

    public function test_returns_dash_when_created_by_is_not_set()
    {
        $row = new SurchargeDate();
        $row->created_by = null;
        $row->created_by_user_type = 'Admin';

        $this->assertSame('-', $this->repository()->getCreatedByUserName($row));
    }

    public function test_keeps_the_attribution_when_the_creator_is_soft_deleted()
    {
        $admin = Admin::factory()->create(['name' => 'Deleted Rate Author']);

        try {
            $admin->delete();
            $this->assertNotNull($admin->fresh()?->deleted_at ?? Admin::withTrashed()->find($admin->id)->deleted_at);

            $row = new SurchargeDate();
            $row->created_by = $admin->id;
            $row->created_by_user_type = 'Admin';

            $this->assertSame('Deleted Rate Author', $this->repository()->getCreatedByUserName($row));
        } finally {
            Admin::withTrashed()->where('id', $admin->id)->forceDelete();
        }
    }

    /**
     * The end to end reproduction: a real surcharge_dates row pointing at a
     * deleted admin must not stop getDataForFilter() from returning.
     */
    public function test_get_data_for_filter_survives_a_surcharge_date_with_a_deleted_creator()
    {
        $admin = Admin::factory()->create(['name' => 'Deleted Rate Author']);
        $admin->delete();

        $surchargeId = DB::table('surcharge_dates')->insertGetId([
            'custom_date_name' => 'Regression orphaned creator',
            'date' => now()->toDateString(),
            'date_range_start' => now()->addDay()->toDateString(),
            'date_range_end' => now()->addMonth()->toDateString(),
            'created_by' => $admin->id,
            'created_by_user_type' => 'Admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $vars = $this->repository()->getDataForFilter();

            $this->assertArrayHasKey('standardRateDates', $vars);

            $loaded = $vars['standardRateDates']->firstWhere('id', $surchargeId);
            $this->assertNotNull($loaded, 'the seeded surcharge date should be in the future set');
            $this->assertSame('Deleted Rate Author', $loaded->created_by_with_name);
        } finally {
            DB::table('surcharge_dates')->where('id', $surchargeId)->delete();
            Admin::withTrashed()->where('id', $admin->id)->forceDelete();
        }
    }

    private function repository(): TourPackageRateRepository
    {
        return app(TourPackageRateRepository::class);
    }
}
