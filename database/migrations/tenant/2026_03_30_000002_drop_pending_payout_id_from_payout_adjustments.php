<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('payout_adjustments', function (Blueprint $table) {
			$table->dropForeign(['pending_payout_id']);
			$table->dropIndex(['pending_payout_id']);
			$table->dropColumn('pending_payout_id');
		});
	}

	public function down(): void
	{
		Schema::table('payout_adjustments', function (Blueprint $table) {
			$table->unsignedBigInteger('pending_payout_id')->nullable()->after('payout_id');
			$table->index('pending_payout_id');
			$table->foreign('pending_payout_id')->references('id')->on('pending_payouts')->onDelete('set null');
		});
	}
};
