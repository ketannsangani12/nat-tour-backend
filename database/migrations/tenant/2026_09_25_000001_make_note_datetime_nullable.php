<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fixes a schema-drift crash: "Column 'note_datetime' cannot be null" on tenant `dixies` (staging)
 * (SQLSTATE[23000] 1048) when creating a booking without a note, which leaves note_datetime unset.
 *
 * Root cause: same as 2026_09_24_000001_make_transaction_number_nullable.php -- commit
 * f2a9e8f614fb85aa5bf94f47b9accb429a378191 ("Updated the migration for the date fields",
 * 2023-07-19) edited the ALREADY-APPLIED `2023_07_18_060410_create_tour_bookings_master_table.php`
 * migration file IN PLACE to change `note_datetime` (among other date columns) from NOT NULL
 * DEFAULT '0000-00-00 00:00:00' to nullable() -- instead of adding a proper ALTER migration.
 * Laravel never re-runs a migration once it's recorded as applied, so any tenant whose schema was
 * created before that edit landed is still stuck on the OLD, NOT NULL definition today, even
 * though the migration file in this repo has said nullable() since 2023.
 *
 * Scoped to just this one column, matching this repo's convention of small, targeted fixes (see
 * 2026_09_24_000001_make_transaction_number_nullable.php) rather than a broad sweep -- the other
 * schema-drift columns from the same 2023-07-19 commit are being reviewed manually.
 *
 * Idempotent: checks information_schema first and issues the ALTER only when the column is
 * currently NOT NULL, so this is a safe no-op on any tenant that's already correct.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tour_bookings_master') || !Schema::hasColumn('tour_bookings_master', 'note_datetime')) {
            return;
        }

        $column = DB::selectOne(
            "SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tour_bookings_master' AND COLUMN_NAME = 'note_datetime'"
        );

        if ($column === null || $column->IS_NULLABLE === 'YES') {
            return; // missing, or already nullable -- nothing to do
        }

        DB::statement("ALTER TABLE `tour_bookings_master` MODIFY `note_datetime` DATETIME NULL");
    }

    public function down(): void
    {
        // Not safely reversible once real NULL rows exist (note_datetime is null by design when a
        // booking has no note) -- MySQL would reject reverting to NOT NULL while any row is
        // genuinely null. Intentionally a no-op rather than guessing a default to backfill with.
    }
};
