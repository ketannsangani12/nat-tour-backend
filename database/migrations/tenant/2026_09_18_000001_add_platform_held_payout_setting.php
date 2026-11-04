<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Per-tenant kill-switch for the "Payout Simplification" settlement model (2027+ tour bookings).
 *
 * Same pattern as AUTO_PAYOUT / DISPUTE_REVERSAL: a plain key/value row in the tenant-scoped `settings`
 * table, read via the existing hasSetting() helper. Deliberately defaults OFF for every tenant this
 * migration runs against — enabling it for a specific tenant is a separate, explicit step.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('settings')->where('key', 'PLATFORM_HELD_PAYOUT_ENABLED')->exists();

        if (!$exists) {
            DB::table('settings')->insert([
                'key' => 'PLATFORM_HELD_PAYOUT_ENABLED',
                'value' => 0,
                'order_number' => 102,
                'comment' => 'No - Disable, Yes - Enable : 2027+ Card/Card-Reader/Payment-Link bookings settle fully on the platform balance instead of a destination charge (Payout Simplification)',
                'input_type' => 'checkbox',
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'PLATFORM_HELD_PAYOUT_ENABLED')->delete();
    }
};
