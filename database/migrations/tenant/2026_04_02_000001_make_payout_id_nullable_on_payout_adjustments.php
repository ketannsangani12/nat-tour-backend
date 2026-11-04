<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('payout_adjustments', function (Blueprint $table) {
			$table->dropForeign(['payout_id']);
		});

		Schema::table('payout_adjustments', function (Blueprint $table) {
			$table->unsignedBigInteger('payout_id')->nullable()->change();
			$table->string('payout_reference', 100)->nullable()->change();
		});

		Schema::table('payout_adjustments', function (Blueprint $table) {
			$table->foreign('payout_id')->references('id')->on('auto_payouts')->onDelete('cascade');
		});
	}

	public function down(): void
	{
		Schema::table('payout_adjustments', function (Blueprint $table) {
			$table->dropForeign(['payout_id']);
		});

		Schema::table('payout_adjustments', function (Blueprint $table) {
			$table->unsignedBigInteger('payout_id')->nullable(false)->change();
			$table->string('payout_reference', 100)->nullable(false)->change();
		});

		Schema::table('payout_adjustments', function (Blueprint $table) {
			$table->foreign('payout_id')->references('id')->on('auto_payouts')->onDelete('cascade');
		});
	}
};
