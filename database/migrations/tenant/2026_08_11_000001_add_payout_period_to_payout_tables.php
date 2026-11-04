<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		// The window a payout used to collect its bookings was derived at run time from
		// last_executed_at / start_date / buffer_days and then thrown away. Once the rule
		// advanced, the window behind a past payout could not be reconstructed, so
		// "why is booking X not in this payout?" was unanswerable from the record.
		//
		// Nullable with no backfill: the window for historical payouts genuinely is not
		// recoverable, and inventing one would be worse than showing "not recorded".
		Schema::table('auto_payouts', function (Blueprint $table) {
			if (!Schema::hasColumn('auto_payouts', 'period_start')) {
				$table->timestamp('period_start')->nullable()->after('booking_count');
			}
			if (!Schema::hasColumn('auto_payouts', 'period_end')) {
				$table->timestamp('period_end')->nullable()->after('period_start');
			}
		});

		Schema::table('pending_payouts', function (Blueprint $table) {
			if (!Schema::hasColumn('pending_payouts', 'period_start')) {
				$table->timestamp('period_start')->nullable()->after('booking_count');
			}
			if (!Schema::hasColumn('pending_payouts', 'period_end')) {
				$table->timestamp('period_end')->nullable()->after('period_start');
			}
		});
	}

	public function down(): void
	{
		Schema::table('auto_payouts', function (Blueprint $table) {
			$table->dropColumn(['period_start', 'period_end']);
		});

		Schema::table('pending_payouts', function (Blueprint $table) {
			$table->dropColumn(['period_start', 'period_end']);
		});
	}
};
