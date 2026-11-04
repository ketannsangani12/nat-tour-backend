<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $exists = DB::select("
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'tour_slot_times_affiliate'
              AND INDEX_NAME = 'idx_tsta_calendar'
            LIMIT 1
        ");

        if ($exists) {
            return;
        }

        Schema::table('tour_slot_times_affiliate', function (Blueprint $table) {
            $table->index(
                ['tour_package_id', 'time_from', 'time_to', 'deleted_by', 'status'],
                'idx_tsta_calendar'
            );
        });
    }

    public function down(): void
    {
        $exists = DB::select("
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'tour_slot_times_affiliate'
              AND INDEX_NAME = 'idx_tsta_calendar'
            LIMIT 1
        ");

        if (!$exists) {
            return;
        }

        Schema::table('tour_slot_times_affiliate', function (Blueprint $table) {
            $table->dropIndex('idx_tsta_calendar');
        });
    }
};
