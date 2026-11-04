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
        if (Schema::hasColumn('tour_bookings_master', 'booking_source')) {
            return;
        }

        try {
            Schema::table('tour_bookings_master', function (Blueprint $table) {
                $table->enum('booking_source', ['phone_call', 'walk_in', 'email', 'other'])
                      ->nullable()
                      ->after('note');
            });
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? null) !== self::DUPLICATE_COLUMN) {
                throw $e;
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('tour_bookings_master', 'booking_source')) {
            return;
        }

        try {
            Schema::table('tour_bookings_master', function (Blueprint $table) {
                $table->dropColumn('booking_source');
            });
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? null) !== self::UNKNOWN_COLUMN) {
                throw $e;
            }
        }
    }
};
