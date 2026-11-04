<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Move infants column to after tourists column
        DB::statement('ALTER TABLE tour_bookings_transact MODIFY COLUMN infants int(11) DEFAULT NULL AFTER tourists');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: Moving column back to original position would require knowing the exact original position
        // For simplicity, we'll just note that this migration reorders the column
        // If you need to reverse, you would need to specify the exact column it should come after
        
        // This is a cosmetic change (column order), so down migration is optional
        // The column will remain functional regardless of position
    }
};
