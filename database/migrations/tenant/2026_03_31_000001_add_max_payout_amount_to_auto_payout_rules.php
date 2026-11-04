<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('auto_payout_rules', function (Blueprint $table) {
			$table->decimal('max_payout_amount', 10, 2)->nullable()->after('minimum_amount');
		});
	}

	public function down(): void
	{
		Schema::table('auto_payout_rules', function (Blueprint $table) {
			$table->dropColumn('max_payout_amount');
		});
	}
};
