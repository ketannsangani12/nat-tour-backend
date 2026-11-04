<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add a 'processing' state used while an operation is being applied.
        DB::statement("ALTER TABLE tour_slot_bulk_operations MODIFY COLUMN status ENUM('queued', 'processing', 'applied', 'failed') NOT NULL DEFAULT 'queued'");

        Schema::table('tour_slot_bulk_operations', function (Blueprint $table) {
            if (!Schema::hasColumn('tour_slot_bulk_operations', 'error')) {
                $table->text('error')->nullable()->after('applied_at'); // why an op failed (shown in the queue)
            }
        });
    }

    public function down(): void
    {
        Schema::table('tour_slot_bulk_operations', function (Blueprint $table) {
            if (Schema::hasColumn('tour_slot_bulk_operations', 'error')) {
                $table->dropColumn('error');
            }
        });

        DB::statement("ALTER TABLE tour_slot_bulk_operations MODIFY COLUMN status ENUM('queued', 'applied', 'failed') NOT NULL DEFAULT 'queued'");
    }
};
