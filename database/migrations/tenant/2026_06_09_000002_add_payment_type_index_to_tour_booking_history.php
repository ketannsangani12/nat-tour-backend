<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // MySQL error 1061: duplicate key name. Guards a concurrent tenant migration run that already created it.
    private const DUPLICATE_KEY_NAME = 1061;

    // MySQL error 1091: can't drop, doesn't exist. Guards a concurrent run that already removed it.
    private const UNKNOWN_KEY = 1091;

    public function up(): void
    {
        $exists = collect(DB::select("SHOW INDEX FROM tour_booking_history WHERE Key_name = 'idx_tbh_booking_payment_type'"))->isNotEmpty();

        if ($exists) {
            return;
        }

        try {
            // Covers the NOT EXISTS correlated subquery in PaymentBreakdownRepository:
            //   WHERE tbh.tour_booking_id = tbm.id AND tbh.payment_type IN ('TripAdvisor', ...)
            // payment_type is TEXT so a 30-char prefix is sufficient for all known OTA values.
            DB::statement('CREATE INDEX idx_tbh_booking_payment_type ON tour_booking_history (tour_booking_id, payment_type(30))');
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? null) !== self::DUPLICATE_KEY_NAME) {
                throw $e;
            }
        }
    }

    public function down(): void
    {
        $exists = collect(DB::select("SHOW INDEX FROM tour_booking_history WHERE Key_name = 'idx_tbh_booking_payment_type'"))->isNotEmpty();

        if (!$exists) {
            return;
        }

        try {
            DB::statement('DROP INDEX idx_tbh_booking_payment_type ON tour_booking_history');
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? null) !== self::UNKNOWN_KEY) {
                throw $e;
            }
        }
    }
};
