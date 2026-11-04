<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('settings')->where('key', 'BOOKING_SOURCE_AFFILIATE')->exists()) {
            DB::table('settings')->insert([
                'key'          => 'BOOKING_SOURCE_AFFILIATE',
                'value'        => 0,
                'order_number' => 70,
                'comment'      => 'Show Booking Source field to Affiliate users on create booking page',
                'input_type'   => 'toggle',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'BOOKING_SOURCE_AFFILIATE')->delete();
    }
};
