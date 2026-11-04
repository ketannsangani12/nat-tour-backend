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
        DB::table('settings')->insert([
            'key' => 'GA4_CODE',
            'value' => null,
            'order_number' => 38,
            'comment' => 'Google Analytics 4 Measurement ID (e.g., G-XXXXXXXXXX)',
            'input_type' => 'text',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->where('key', 'GA4_CODE')->delete();
    }
};
