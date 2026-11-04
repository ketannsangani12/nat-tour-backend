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
        // Add CHD setting
        DB::table('settings')->insert([
            'key' => 'ENABLE_CHD',
            'value' => 0,
            'order_number' => 45,
            'comment' => 'Enable CHD in Third Party Platform filter',
            'input_type' => 'checkbox',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Add Lagunacreek setting
        DB::table('settings')->insert([
            'key' => 'ENABLE_LAGUNACREEK',
            'value' => 0,
            'order_number' => 46,
            'comment' => 'Enable Lagunacreek in Third Party Platform filter',
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
        DB::table('settings')->where('key', 'ENABLE_CHD')->delete();
        DB::table('settings')->where('key', 'ENABLE_LAGUNACREEK')->delete();
    }
};
