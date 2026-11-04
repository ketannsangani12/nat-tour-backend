<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add composite index for efficient duplicate checking
        // Using raw SQL to specify key length for TEXT column
        DB::statement('CREATE INDEX idx_booking_operation_details ON tour_booking_history (tour_booking_id, operation, details(100))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX idx_booking_operation_details ON tour_booking_history');
    }
};
