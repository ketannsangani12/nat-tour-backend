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
        if (!Schema::hasColumn('customers', 'ota_affiliate_percentage')) {
            try {
                Schema::table('customers', function (Blueprint $table) {
                    $table->decimal('ota_affiliate_percentage', 10, 8)->default(0)->after('is_ota');
                });
            } catch (QueryException $e) {
                if ((int) ($e->errorInfo[1] ?? null) !== self::DUPLICATE_COLUMN) {
                    throw $e;
                }
            }
        }

        if (!Schema::hasColumn('customers', 'next_year_ota_affiliate_percentage')) {
            try {
                Schema::table('customers', function (Blueprint $table) {
                    $table->decimal('next_year_ota_affiliate_percentage', 10, 8)->default(0)->after('ota_affiliate_percentage');
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
        foreach (['ota_affiliate_percentage', 'next_year_ota_affiliate_percentage'] as $column) {
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
