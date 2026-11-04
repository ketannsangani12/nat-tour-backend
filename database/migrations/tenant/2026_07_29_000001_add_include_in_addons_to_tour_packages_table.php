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
              AND TABLE_NAME = 'tour_packages'
              AND COLUMN_NAME = 'include_in_addons'
        ");

        if (($exists[0]->cnt ?? 0) === 0) {
            try {
                Schema::table('tour_packages', function (Blueprint $table) {
                    $table->boolean('include_in_addons')->after('include_in_operation_board')->default(0)->comment('0-No, 1-Yes');
                });
            } catch (QueryException $e) {
                // 42S21 = duplicate column. Another process (concurrent tenant
                // migration run) won the race and already applied this change
                // since the check above ran.
                if ($e->getCode() !== '42S21') {
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
              AND TABLE_NAME = 'tour_packages'
              AND COLUMN_NAME = 'include_in_addons'
        ");

        if (($exists[0]->cnt ?? 0) > 0) {
            Schema::table('tour_packages', function (Blueprint $table) {
                $table->dropColumn('include_in_addons');
            });
        }
    }
};
