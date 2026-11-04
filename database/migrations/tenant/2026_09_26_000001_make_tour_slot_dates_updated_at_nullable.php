<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fixes a schema-drift crash: "Column 'updated_at' cannot be null" on tenant `dixies` (staging)
 * (SQLSTATE[23000] 1048) when repeating a tour slot, since TourDateSlot::booted() deliberately
 * sets `updated_at = null` on creation (see app/Models/TourDateSlot.php) to distinguish
 * never-modified rows from modified ones.
 *
 * Root cause: same as 2026_09_24_000001_make_transaction_number_nullable.php and
 * 2026_09_25_000001_make_note_datetime_nullable.php -- commit
 * f2a9e8f614fb85aa5bf94f47b9accb429a378191 ("Updated the migration for the date fields",
 * 2023-07-19) edited the ALREADY-APPLIED `2023_07_18_060410_create_tour_slot_dates_table.php`
 * migration file IN PLACE to change `updated_at` (among other date columns) from NOT NULL
 * DEFAULT '0000-00-00 00:00:00' to nullable() -- instead of adding a proper ALTER migration.
 * Laravel never re-runs a migration once it's recorded as applied, so any tenant whose schema was
 * created before that edit landed is still stuck on the OLD, NOT NULL definition today, even
 * though the migration file in this repo has said nullable() since 2023.
 *
 * Scoped to just this one column, matching this repo's convention of small, targeted fixes rather
 * than a broad sweep -- the other schema-drift columns from the same 2023-07-19 commit are being
 * reviewed manually.
 *
 * Idempotent: checks information_schema first and issues the ALTER only when the column is
 * currently NOT NULL, so this is a safe no-op on any tenant that's already correct.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tour_slot_dates') || !Schema::hasColumn('tour_slot_dates', 'updated_at')) {
            return;
        }

        $column = DB::selectOne(
            "SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tour_slot_dates' AND COLUMN_NAME = 'updated_at'"
        );

        if ($column === null || $column->IS_NULLABLE === 'YES') {
            return; // missing, or already nullable -- nothing to do
        }

        DB::statement("ALTER TABLE `tour_slot_dates` MODIFY `updated_at` DATETIME NULL");
    }

    public function down(): void
    {
        // Not safely reversible once real NULL rows exist (updated_at is null by design on
        // creation, per TourDateSlot::booted()) -- MySQL would reject reverting to NOT NULL while
        // any row is genuinely null. Intentionally a no-op rather than guessing a default to
        // backfill with.
    }
};
