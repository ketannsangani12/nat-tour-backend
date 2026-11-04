<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // MySQL error 1060: duplicate column. Guards a concurrent tenant migration run that already added it.
    private const DUPLICATE_COLUMN = 1060;

    // MySQL error 1091: can't drop, doesn't exist. Guards a concurrent run that already removed it.
    private const UNKNOWN_COLUMN = 1091;

    public function up(): void
    {
        if (!Schema::hasColumn('customers', 'min_pax')) {
            try {
                Schema::table('customers', function (Blueprint $table) {
                    // Minimum pax an affiliate must sell per booking; null = no restriction
                    $table->integer('min_pax')->nullable()->after('is_ota')
                        ->comment('Minimum pax allowed in a booking for this affiliate; null means no restriction');
                });
            } catch (QueryException $e) {
                if ((int) ($e->errorInfo[1] ?? null) !== self::DUPLICATE_COLUMN) {
                    throw $e;
                }
            }
        }

        if (!Schema::hasColumn('customers', 'max_pax')) {
            try {
                Schema::table('customers', function (Blueprint $table) {
                    // Maximum pax an affiliate can sell per booking; null = no restriction
                    $table->integer('max_pax')->nullable()->after('min_pax')
                        ->comment('Maximum pax allowed in a booking for this affiliate; null means no restriction');
                });
            } catch (QueryException $e) {
                if ((int) ($e->errorInfo[1] ?? null) !== self::DUPLICATE_COLUMN) {
                    throw $e;
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['min_pax', 'max_pax'] as $column) {
            if (!Schema::hasColumn('customers', $column)) {
                continue;
            }

            try {
                Schema::table('customers', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            } catch (QueryException $e) {
                if ((int) ($e->errorInfo[1] ?? null) !== self::UNKNOWN_COLUMN) {
                    throw $e;
                }
            }
        }
    }
};
