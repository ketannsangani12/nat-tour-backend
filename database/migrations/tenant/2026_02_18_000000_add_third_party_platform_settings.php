<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add TripAdvisor setting
        DB::table('settings')->insert([
            'key' => 'ENABLE_TRIPADVISOR',
            'value' => 1,
            'order_number' => 41,
            'comment' => 'Enable TripAdvisor in Third Party Platform filter',
            'input_type' => 'checkbox',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Add Headout setting
        DB::table('settings')->insert([
            'key' => 'ENABLE_HEADOUT',
            'value' => 1,
            'order_number' => 42,
            'comment' => 'Enable Headout in Third Party Platform filter',
            'input_type' => 'checkbox',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Add GetYourGuide setting
        DB::table('settings')->insert([
            'key' => 'ENABLE_GETYOURGUIDE',
            'value' => 1,
            'order_number' => 43,
            'comment' => 'Enable GetYourGuide in Third Party Platform filter',
            'input_type' => 'checkbox',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Add FareHarbor setting
        DB::table('settings')->insert([
            'key' => 'ENABLE_FAREHARBOR',
            'value' => 1,
            'order_number' => 44,
            'comment' => 'Enable FareHarbor in Third Party Platform filter',
            'input_type' => 'checkbox',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->where('key', 'ENABLE_TRIPADVISOR')->delete();
        DB::table('settings')->where('key', 'ENABLE_HEADOUT')->delete();
        DB::table('settings')->where('key', 'ENABLE_GETYOURGUIDE')->delete();
        DB::table('settings')->where('key', 'ENABLE_FAREHARBOR')->delete();
    }
};
