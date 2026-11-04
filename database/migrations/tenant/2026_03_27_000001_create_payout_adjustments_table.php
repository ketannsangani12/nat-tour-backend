<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('payout_adjustments', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('payout_id');
			$table->string('payout_reference', 100);
			$table->unsignedBigInteger('booking_id');
			$table->enum('type', ['credit', 'debit']);
			$table->decimal('gross_amount', 10, 2);
			$table->decimal('platform_fee', 10, 2)->default(0);
			$table->decimal('net_amount', 10, 2);
			$table->text('reason');
			$table->json('source_history_ids');
			$table->enum('status', ['pending', 'applied', 'voided'])->default('pending');
			$table->unsignedBigInteger('applied_in_payout_id')->nullable();
			$table->dateTime('applied_at')->nullable();
			$table->dateTime('detected_at');
			$table->unsignedBigInteger('voided_by')->nullable();
			$table->dateTime('voided_at')->nullable();
			$table->text('void_reason')->nullable();
			$table->timestamps();

			$table->index('booking_id');
			$table->index('payout_id');
			$table->index('status');
			$table->index('applied_in_payout_id');

			$table->foreign('payout_id')->references('id')->on('auto_payouts')->onDelete('cascade');
			$table->foreign('booking_id')->references('id')->on('tour_bookings_master')->onDelete('cascade');
			$table->foreign('applied_in_payout_id')->references('id')->on('auto_payouts')->onDelete('set null');
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('payout_adjustments');
	}
};
