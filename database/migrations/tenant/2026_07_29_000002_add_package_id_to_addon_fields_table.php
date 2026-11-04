<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $exists = DB::select("
            SELECT COUNT(*) as cnt
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'addon_fields'
              AND COLUMN_NAME = 'package_id'
        ");

        if (($exists[0]->cnt ?? 0) === 0) {
            try {
                Schema::table('addon_fields', function (Blueprint $table) {
                    $table->foreignId('package_id')->nullable()->after('category_id')
                        ->constrained('tour_packages')->nullOnDelete();
                });
            } catch (QueryException $e) {
                // 42S21 = duplicate column, 42000/1826 = duplicate FK constraint name.
                // Another process (concurrent tenant migration run) won the race and
                // already applied this change since the check above ran.
                if (! in_array($e->getCode(), ['42S21', '42000'], true)) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $exists = DB::select("
            SELECT COUNT(*) as cnt
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'addon_fields'
              AND COLUMN_NAME = 'package_id'
        ");

        if (($exists[0]->cnt ?? 0) > 0) {
            Schema::table('addon_fields', function (Blueprint $table) {
                $table->dropForeign(['package_id']);
                $table->dropColumn('package_id');
            });
        }
    }
};
