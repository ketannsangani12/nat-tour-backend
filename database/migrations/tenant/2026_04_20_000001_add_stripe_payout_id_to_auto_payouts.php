<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('auto_payouts', function (Blueprint $table) {
			if (!Schema::hasColumn('auto_payouts', 'stripe_payout_id')) {
				$table->string('stripe_payout_id', 255)->nullable()->unique()->after('payout_reference');
			}
		});
	}

	public function down(): void
	{
		Schema::table('auto_payouts', function (Blueprint $table) {
			if (Schema::hasColumn('auto_payouts', 'stripe_payout_id')) {
				$table->dropUnique(['stripe_payout_id']);
				$table->dropColumn('stripe_payout_id');
			}
		});
	}
};
