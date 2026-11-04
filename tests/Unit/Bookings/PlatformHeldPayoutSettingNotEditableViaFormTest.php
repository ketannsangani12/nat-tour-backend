<?php

namespace Tests\Unit\Bookings;

use App\Http\Controllers\Admin\SettingController;
use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * PLATFORM_HELD_PAYOUT_ENABLED (the "Payout Simplification" kill-switch, see
 * database/migrations/tenant/2026_09_18_000001_add_platform_held_payout_setting.php) controls
 * which Stripe settlement model live 2027+ bookings use. It's deliberately removed from the
 * generic on/off settings UI (setting-add-edit.blade.php) and now refused by
 * SettingController::update() too, so it can only ever be changed by a deliberate direct
 * database update -- never an accidental toggle click on the settings page.
 */
class PlatformHeldPayoutSettingNotEditableViaFormTest extends TestCase
{
    private function authAsAdmin(): void
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
    }

    public function test_update_ignores_a_submitted_value_for_the_payout_simplification_setting(): void
    {
        $this->authAsAdmin();

        $setting = Setting::where('key', 'PLATFORM_HELD_PAYOUT_ENABLED')->first();
        $this->assertNotNull($setting, 'Migration 2026_09_18_000001 must have seeded this setting row');

        $originalValue = $setting->value;
        $flippedValue = $originalValue == 1 ? 0 : 1;

        $request = Request::create('/setting/update', 'POST', [
            (string) $setting->id => $flippedValue,
        ]);

        app(SettingController::class)->update($request);

        $this->assertEquals(
            $originalValue,
            Setting::find($setting->id)->value,
            'PLATFORM_HELD_PAYOUT_ENABLED must never change via the settings form, regardless of what is submitted'
        );
    }

    public function test_update_still_saves_an_unrelated_setting_in_the_same_request(): void
    {
        $this->authAsAdmin();

        $platformHeld = Setting::where('key', 'PLATFORM_HELD_PAYOUT_ENABLED')->first();
        $other = Setting::where('key', '!=', 'PLATFORM_HELD_PAYOUT_ENABLED')->first();
        $this->assertNotNull($other, 'Need at least one other settings row to prove the guard is scoped correctly');

        $originalOtherValue = $other->value;
        $flippedOtherValue = $originalOtherValue == 1 ? 0 : 1;

        try {
            $request = Request::create('/setting/update', 'POST', [
                (string) $platformHeld->id => ($platformHeld->value == 1 ? 0 : 1),
                (string) $other->id => $flippedOtherValue,
            ]);

            app(SettingController::class)->update($request);

            $this->assertEquals(
                (string) $flippedOtherValue,
                (string) Setting::find($other->id)->value,
                'The guard must only block PLATFORM_HELD_PAYOUT_ENABLED, not every setting submitted alongside it'
            );
        } finally {
            $other->value = $originalOtherValue;
            $other->save();
        }
    }
}
