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

    private const TABLES = ['customers', 'tour_operator_staff', 'admins'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            if (Schema::hasColumn($tableName, 'default_booking_source')) {
                continue;
            }

            try {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->enum('default_booking_source', ['phone_call', 'walk_in', 'email', 'other'])
                          ->nullable()
                          ->after('profile_photo_path');
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
        foreach (self::TABLES as $tableName) {
            if (!Schema::hasColumn($tableName, 'default_booking_source')) {
                continue;
            }

            try {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('default_booking_source');
                });
            } catch (QueryException $e) {
                if ((int) ($e->errorInfo[1] ?? null) !== self::UNKNOWN_COLUMN) {
                    throw $e;
                }
            }
        }
    }
};
