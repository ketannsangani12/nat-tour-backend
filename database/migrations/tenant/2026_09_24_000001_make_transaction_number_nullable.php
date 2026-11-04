<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fixes a schema-drift crash: "Column 'transaction_number' cannot be null" on tenant `dixies`
 * (SQLSTATE[23000] 1048) when StripeService::stripeCreatePaymentIntentForMakePayment() writes an
 * Initialized reservation row with transaction_number = null.
 *
 * Root cause: commit 05ae4de418919e655de19a7430615439db266fda ("Updated the migration for the
 * tenants", 2023-07-19 19:50, about an hour after the sibling "date fields" commit) edited the
 * ALREADY-APPLIED `2023_07_18_060410_create_transactions_table.php` migration file IN PLACE to
 * change `transaction_number` from NOT NULL DEFAULT '' to nullable() — instead of adding a proper
 * ALTER migration. Laravel never re-runs a migration once it's recorded as applied, so any tenant
 * whose schema was created before that edit landed (dixies among them) is still stuck on the OLD,
 * NOT NULL definition today, even though the migration file in this repo has said nullable() since
 * 2023. The application code was separately fixed this cycle to stop writing '' and write null
 * instead (matching how a reservation row's transaction_number is genuinely unset until Stripe
 * returns a real PaymentIntent id) — correct behavior on a schema that's actually nullable, but a
 * hard crash on a tenant still stuck on the pre-2023-07-19 definition.
 *
 * Scoped to just this one column, matching this repo's convention of small, targeted fixes rather
 * than a broad sweep — see docs/payments (this project's other schema-drift columns, e.g. the date
 * columns from the sibling commit, are being reviewed manually rather than swept automatically).
 *
 * Idempotent: checks information_schema first and issues the ALTER only when the column is
 * currently NOT NULL, so this is a safe no-op on any tenant that's already correct.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('transactions') || !Schema::hasColumn('transactions', 'transaction_number')) {
            return;
        }

        $column = DB::selectOne(
            "SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transactions' AND COLUMN_NAME = 'transaction_number'"
        );

        if ($column === null || $column->IS_NULLABLE === 'YES') {
            return; // missing, or already nullable -- nothing to do
        }

        DB::statement("ALTER TABLE `transactions` MODIFY `transaction_number` VARCHAR(50) NULL");
    }

    public function down(): void
    {
        // Not safely reversible once real NULL rows exist (a reservation row's transaction_number
        // is null by design until Stripe assigns a real PaymentIntent id) -- MySQL would reject
        // reverting to NOT NULL while any row is genuinely null. Intentionally a no-op rather than
        // guessing a default to backfill with.
    }
};
