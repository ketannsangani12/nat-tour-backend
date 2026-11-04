<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = collect(DB::select("SHOW INDEX FROM tour_bookings_master WHERE Key_name = 'idx_tbm_cart_id'"))->isNotEmpty();
        if (!$exists) {
            DB::statement('ALTER TABLE tour_bookings_master ADD INDEX idx_tbm_cart_id (cart_id)');
        }
    }

    public function down(): void
    {
        $exists = collect(DB::select("SHOW INDEX FROM tour_bookings_master WHERE Key_name = 'idx_tbm_cart_id'"))->isNotEmpty();
        if ($exists) {
            DB::statement('ALTER TABLE tour_bookings_master DROP INDEX idx_tbm_cart_id');
        }
    }
};
