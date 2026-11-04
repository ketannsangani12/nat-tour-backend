<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Add an 'expired' state used when a queued op is auto-expired at end of day.
        DB::statement("ALTER TABLE tour_slot_bulk_operations MODIFY COLUMN status ENUM('queued', 'processing', 'applied', 'failed', 'expired') NOT NULL DEFAULT 'queued'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE tour_slot_bulk_operations MODIFY COLUMN status ENUM('queued', 'processing', 'applied', 'failed') NOT NULL DEFAULT 'queued'");
    }
};
