<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations for calendar performance optimization.
     */
    public function up(): void
    {
        // Add composite indexes for calendar queries (MySQL compatible)
        
        try {
            // Index for tour_slot_times calendar queries
            DB::statement('CREATE INDEX idx_tour_slot_times_calendar 
                ON tour_slot_times (tour_package_id, date, time_from, deleted_by, hide_slot)');
        } catch (\Exception $e) {
            // Index might already exist, ignore
        }
        
        try {
            // Index for tour_bookings_master calendar queries
            DB::statement('CREATE INDEX idx_tour_bookings_calendar 
                ON tour_bookings_master (tour_slot_id, tour_package_id, status, customer_id)');
        } catch (\Exception $e) {
            // Index might already exist, ignore
        }
        
        try {
            // Index for tour_package_families calendar queries
            DB::statement('CREATE INDEX idx_package_families_calendar 
                ON tour_package_families (tour_package_id, time_from, relation_year, group_index, is_parent)');
        } catch (\Exception $e) {
            // Index might already exist, ignore
        }
        
        try {
            // Index for tour_slot_dates calendar queries
            DB::statement('CREATE INDEX idx_tour_slot_dates_calendar 
                ON tour_slot_dates (tour_package_id, date, status, deleted_by)');
        } catch (\Exception $e) {
            // Index might already exist, ignore
        }
        
        try {
            // Index for tour_booking_payment_links
            DB::statement('CREATE INDEX idx_payment_links_order_status 
                ON tour_booking_payment_links (order_id, status)');
        } catch (\Exception $e) {
            // Index might already exist, ignore
        }
        
        try {
            // Index for tour_booking_history
            DB::statement('CREATE INDEX idx_booking_history_booking_id 
                ON tour_booking_history (tour_booking_id)');
        } catch (\Exception $e) {
            // Index might already exist, ignore
        }
        
        try {
            // Index for affiliate_slot_times
            DB::statement('CREATE INDEX idx_affiliate_slot_times_calendar 
                ON affiliate_slot_times (tour_slot_time_id, affiliate_id, deleted_by)');
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
            DB::statement('DROP INDEX idx_tour_slot_times_calendar ON tour_slot_times');
        } catch (\Exception $e) {
            // Index might not exist, ignore
        }
        
        try {
            DB::statement('DROP INDEX idx_tour_bookings_calendar ON tour_bookings_master');
        } catch (\Exception $e) {
            // Index might not exist, ignore
        }
        
        try {
            DB::statement('DROP INDEX idx_package_families_calendar ON tour_package_families');
        } catch (\Exception $e) {
            // Index might not exist, ignore
        }
        
        try {
            DB::statement('DROP INDEX idx_tour_slot_dates_calendar ON tour_slot_dates');
        } catch (\Exception $e) {
            // Index might not exist, ignore
        }
        
        try {
            DB::statement('DROP INDEX idx_payment_links_order_status ON tour_booking_payment_links');
        } catch (\Exception $e) {
            // Index might not exist, ignore
        }
        
        try {
            DB::statement('DROP INDEX idx_booking_history_booking_id ON tour_booking_history');
        } catch (\Exception $e) {
            // Index might not exist, ignore
        }
        
        try {
            DB::statement('DROP INDEX idx_affiliate_slot_times_calendar ON affiliate_slot_times');
        } catch (\Exception $e) {
            // Index might not exist, ignore
        }
    }
};