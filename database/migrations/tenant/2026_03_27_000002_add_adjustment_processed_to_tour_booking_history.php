<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('tour_booking_history', function (Blueprint $table) {
			$table->boolean('adjustment_processed')->default(false)->after('is_processed');
			$table->index(['adjustment_processed', 'operation'], 'idx_adj_processed_operation');
		});
	}

	public function down(): void
	{
		Schema::table('tour_booking_history', function (Blueprint $table) {
			$table->dropIndex('idx_adj_processed_operation');
			$table->dropColumn('adjustment_processed');
		});
	}
};
