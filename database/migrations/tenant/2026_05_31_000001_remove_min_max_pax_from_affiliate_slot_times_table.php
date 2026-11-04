<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // MySQL error 1060: duplicate column. Guards a concurrent tenant migration run that already added it back.
    private const DUPLICATE_COLUMN = 1060;

    // MySQL error 1091: can't drop, doesn't exist. Guards a concurrent run that already removed it.
    private const UNKNOWN_COLUMN = 1091;

    public function up(): void
    {
        foreach (['min_pax', 'max_pax'] as $column) {
            if (!Schema::hasColumn('affiliate_slot_times', $column)) {
                continue;
            }

            try {
                Schema::table('affiliate_slot_times', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            } catch (QueryException $e) {
                if ((int) ($e->errorInfo[1] ?? null) !== self::UNKNOWN_COLUMN) {
                    throw $e;
                }
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('affiliate_slot_times', 'min_pax')) {
            try {
                Schema::table('affiliate_slot_times', function (Blueprint $table) {
                    $table->integer('min_pax')->default(0)->after('seat_limit')
                        ->comment('Minimum pax allowed in a booking for the slot; 0 means no restriction');
                });
            } catch (QueryException $e) {
                if ((int) ($e->errorInfo[1] ?? null) !== self::DUPLICATE_COLUMN) {
                    throw $e;
                }
            }
        }

        if (!Schema::hasColumn('affiliate_slot_times', 'max_pax')) {
            try {
                Schema::table('affiliate_slot_times', function (Blueprint $table) {
                    $table->integer('max_pax')->default(0)->after('min_pax')
                        ->comment('Maximum pax allowed in a booking for the slot; 0 means no restriction');
                });
            } catch (QueryException $e) {
                if ((int) ($e->errorInfo[1] ?? null) !== self::DUPLICATE_COLUMN) {
                    throw $e;
                }
            }
        }
    }
};
