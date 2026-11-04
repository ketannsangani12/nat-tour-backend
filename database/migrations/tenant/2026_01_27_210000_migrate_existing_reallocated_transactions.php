<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Update existing transactions to set is_reallocated = true for historical
     * split-related transactions where the booking tour date is in 2026.
     *
     * @return void
     */
    public function up(): void
    {
        // Strategy 1: Mark refund transactions from splits (tour date in 2026 only)
        // These have tour_booking_history records with:
        // - operation = 'Refund'
        // - details contains "from":"Extend Booking"
        // - booking tour_date is in 2026

        $splitRefundTransactionIds = DB::table('tour_booking_history as tbh')
            ->join('tour_bookings_master as tbm', 'tbh.tour_booking_id', '=', 'tbm.id')
            ->join('tour_slot_times as tst', 'tbm.tour_slot_id', '=', 'tst.id')
            ->where('tbh.operation', 'Refund')
            ->where('tbh.details', 'LIKE', '%"from":"Extend Booking"%')
            ->whereYear('tst.date', '=', 2026)
            ->whereNotNull('tbh.transaction_id')
            ->pluck('tbh.transaction_id')
            ->unique()
            ->toArray();

        $refundCount = 0;
        if (!empty($splitRefundTransactionIds)) {
            // Process in chunks to avoid "too many placeholders" error
            // MySQL has a limit of ~65,535 placeholders
            $chunks = array_chunk($splitRefundTransactionIds, 1000);

            foreach ($chunks as $chunk) {
                $refundCount += DB::table('transactions')
                    ->whereIn('id', $chunk)
                    ->update(['is_reallocated' => true]);
            }
        }

        // Strategy 2: Mark payment transactions for extended bookings (tour date in 2026 only)
        // These bookings have tour_booking_history records with:
        // - operation = 'Split Booking'
        // - booking tour_date is in 2026

        $extendedBookingIds = DB::table('tour_booking_history as tbh')
            ->join('tour_bookings_master as tbm', 'tbh.tour_booking_id', '=', 'tbm.id')
            ->join('tour_slot_times as tst', 'tbm.tour_slot_id', '=', 'tst.id')
            ->where('tbh.operation', 'Split Booking')
            ->whereYear('tst.date', '=', 2026)
            ->pluck('tbh.tour_booking_id')
            ->unique()
            ->toArray();

        $paymentCount = 0;
        if (!empty($extendedBookingIds)) {
            // Process in chunks to avoid "too many placeholders" error
            $chunks = array_chunk($extendedBookingIds, 1000);

            foreach ($chunks as $chunk) {
                $paymentCount += DB::table('transactions')
                    ->whereIn('tour_booking_id', $chunk)
                    ->where('status', 'Completed')
                    ->update(['is_reallocated' => true]);
            }
        }

        // Log results
        Log::info("Data migration completed (2026 tour dates): {$refundCount} refund transactions and {$paymentCount} payment transactions marked as reallocated");

        echo "Data migration completed (2026 tour dates only):\n";
        echo "- {$refundCount} refund transactions marked as reallocated\n";
        echo "- {$paymentCount} payment transactions marked as reallocated\n";
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        // Reset all is_reallocated flags
        $count = DB::table('transactions')
            ->where('is_reallocated', true)
            ->update(['is_reallocated' => false]);

        Log::info("Data migration rollback: {$count} transactions reset to is_reallocated = false");

        echo "Data migration rollback: {$count} transactions reset\n";
    }
};
