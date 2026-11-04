<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations for additional calendar performance optimization.
     */
    public function up(): void
    {
        try {
            // Index for transactions (for booking details query optimization)
            DB::statement('CREATE INDEX idx_transactions_booking_status 
                ON transactions (tour_booking_id, status, payment_type)');
        } catch (\Exception $e) {
            // Index might already exist, ignore
        }
        
        try {
            // Index for tour_bookings_transact (for rate groups optimization)
            DB::statement('CREATE INDEX idx_bookings_transact_booking_deleted 
                ON tour_bookings_transact (tour_booking_id, deleted_by)');
        } catch (\Exception $e) {
            // Index might already exist, ignore
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement('DROP INDEX idx_transactions_booking_status ON transactions');
        } catch (\Exception $e) {
            // Index might not exist, ignore
        }
        
        try {
            DB::statement('DROP INDEX idx_bookings_transact_booking_deleted ON tour_bookings_transact');
        } catch (\Exception $e) {
            // Index might not exist, ignore
        }
    }
};