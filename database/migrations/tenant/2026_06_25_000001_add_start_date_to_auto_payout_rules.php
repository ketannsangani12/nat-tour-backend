<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('auto_payout_rules', function (Blueprint $table) {
			// First-run floor: when last_executed_at is null, the payout window
			// starts here instead of falling back to created_at. Lets an operator
			// control how far back the first payout reaches. Nullable so existing
			// rules are unaffected until a start date is set.
			$table->date('start_date')->nullable()->after('buffer_days');
		});
	}

	public function down(): void
	{
		Schema::table('auto_payout_rules', function (Blueprint $table) {
			$table->dropColumn('start_date');
		});
	}
};
