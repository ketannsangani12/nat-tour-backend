<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Add indexes for the auto-payout sweep query. The query in
 * PayoutApprovalService::calculateAccountPayoutAmount filters
 * tour_bookings_master.payout_made = 0 (no index today) and
 * transactions on (via, status, request_from) (partial single-column
 * coverage today). Without these, the query falls back to full-table
 * scans on high-volume tenants.
 */
return new class extends Migration
{
	public function up(): void
	{
		if (Schema::hasTable('tour_bookings_master') && !$this->indexExists('tour_bookings_master', 'idx_tour_bookings_payout_made')) {
			Schema::table('tour_bookings_master', function (Blueprint $table) {
				$table->index('payout_made', 'idx_tour_bookings_payout_made');
			});
		}

		if (Schema::hasTable('transactions') && !$this->indexExists('transactions', 'idx_transactions_payout_filter')) {
			Schema::table('transactions', function (Blueprint $table) {
				$table->index(['via', 'status', 'request_from'], 'idx_transactions_payout_filter');
			});
		}
	}

	public function down(): void
	{
		if (Schema::hasTable('tour_bookings_master') && $this->indexExists('tour_bookings_master', 'idx_tour_bookings_payout_made')) {
			Schema::table('tour_bookings_master', function (Blueprint $table) {
				$table->dropIndex('idx_tour_bookings_payout_made');
			});
		}

		if (Schema::hasTable('transactions') && $this->indexExists('transactions', 'idx_transactions_payout_filter')) {
			Schema::table('transactions', function (Blueprint $table) {
				$table->dropIndex('idx_transactions_payout_filter');
			});
		}
	}

	private function indexExists(string $table, string $index): bool
	{
		$result = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index]);
		return !empty($result);
	}
};
