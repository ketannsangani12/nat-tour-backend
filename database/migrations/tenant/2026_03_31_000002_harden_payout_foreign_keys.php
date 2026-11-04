<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		// Change auto_payouts.rule_id from CASCADE to SET NULL
		Schema::table('auto_payouts', function (Blueprint $table) {
			$table->dropForeign(['rule_id']);
			$table->unsignedBigInteger('rule_id')->nullable()->change();
			$table->foreign('rule_id')->references('id')->on('auto_payout_rules')->onDelete('set null');
		});

		// Change pending_payouts.rule_id from CASCADE to SET NULL
		Schema::table('pending_payouts', function (Blueprint $table) {
			$table->dropForeign(['rule_id']);
			$table->unsignedBigInteger('rule_id')->nullable()->change();
			$table->foreign('rule_id')->references('id')->on('auto_payout_rules')->onDelete('set null');
		});

		// Add payout_reference index for adjustment lookups
		Schema::table('payout_adjustments', function (Blueprint $table) {
			$table->index('payout_reference', 'idx_adj_payout_reference');
		});
	}

	public function down(): void
	{
		Schema::table('payout_adjustments', function (Blueprint $table) {
			$table->dropIndex('idx_adj_payout_reference');
		});

		Schema::table('pending_payouts', function (Blueprint $table) {
			$table->dropForeign(['rule_id']);
			$table->unsignedBigInteger('rule_id')->nullable(false)->change();
			$table->foreign('rule_id')->references('id')->on('auto_payout_rules')->onDelete('cascade');
		});

		Schema::table('auto_payouts', function (Blueprint $table) {
			$table->dropForeign(['rule_id']);
			$table->unsignedBigInteger('rule_id')->nullable(false)->change();
			$table->foreign('rule_id')->references('id')->on('auto_payout_rules')->onDelete('cascade');
		});
	}
};
