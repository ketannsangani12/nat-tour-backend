<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::select("
            SELECT COUNT(*) as cnt
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'tour_booking_addon_transactions'
              AND COLUMN_NAME = 'deleted_at'
        ");

        if (($exists[0]->cnt ?? 0) === 0) {
            Schema::table('tour_booking_addon_transactions', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        $exists = DB::select("
            SELECT COUNT(*) as cnt
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'tour_booking_addon_transactions'
              AND COLUMN_NAME = 'deleted_at'
        ");

        if (($exists[0]->cnt ?? 0) > 0) {
            Schema::table('tour_booking_addon_transactions', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
