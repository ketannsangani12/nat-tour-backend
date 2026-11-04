<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Per-tenant toggle for stopNextYearBookings() (app/helpers.php): the "next year" is always
 * computed as date('Y') + 1 at check time, so this one setting keeps working every year without
 * needing a new migration. Deliberately defaults OFF (matches today's blanket production block).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('settings')->where('key', 'ALLOW_NEXT_YEAR_BOOKINGS')->exists()) {
            DB::table('settings')->insert([
                'key'          => 'ALLOW_NEXT_YEAR_BOOKINGS',
                'value'        => 0,
                'order_number' => 103,
                'comment'      => 'No - Disable, Yes - Enable : Allow bookings for next calendar year in production',
                'input_type'   => 'toggle',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'ALLOW_NEXT_YEAR_BOOKINGS')->delete();
    }
};
