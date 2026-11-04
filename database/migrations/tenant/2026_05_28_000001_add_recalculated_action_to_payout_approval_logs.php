<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payout_approval_logs')) {
            return;
        }

        // PayoutApprovalService::recalculatePendingPayout logs action 'recalculated',
        // which was not in the enum -- MySQL (non-strict mode) silently stored an
        // empty string, so the recalculation audit trail was unqueryable.
        DB::statement("ALTER TABLE payout_approval_logs MODIFY COLUMN action ENUM('created','approved','rejected','executed','failed','cancelled','recalculated') NOT NULL");

        // Backfill the rows that were silently written as '' by the recalc path.
        // Scoped to the recalculation notes so no other action is touched. Idempotent.
        DB::table('payout_approval_logs')
            ->where('action', '')
            ->where('notes', 'like', 'Payout recalculated%')
            ->update(['action' => 'recalculated']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('payout_approval_logs')) {
            return;
        }

        // Revert backfilled rows before narrowing the enum so they remain valid.
        DB::table('payout_approval_logs')
            ->where('action', 'recalculated')
            ->update(['action' => '']);

        DB::statement("ALTER TABLE payout_approval_logs MODIFY COLUMN action ENUM('created','approved','rejected','executed','failed','cancelled') NOT NULL");
    }
};
