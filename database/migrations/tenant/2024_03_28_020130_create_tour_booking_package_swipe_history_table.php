<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tour_booking_package_swipe_history', function (Blueprint $table) {
            $table->id();
			$table->unsignedBigInteger('tour_booking_id')->index('tour_booking_id')->nullable()->index('tour_booking_package_swipe_history_tour_booking_id_foreign');
			$table->unsignedBigInteger('old_tour_package_id')->index('old_tour_package_id')->nullable()->index('tour_booking_package_swipe_history_old_tour_package_id');
			$table->unsignedBigInteger('new_tour_package_id')->index('new_tour_package_id')->nullable()->index('tour_booking_package_swipe_history_new_tour_package_id');
			$table->unsignedSmallInteger('tourists')->default(0);
			$table->text('payment_method')->nullable()->comment('Payment method of the booking');
			$table->dateTime('created_at')->useCurrent()->nullable()->index();
			$table->unsignedBigInteger('created_by')->nullable()->index('created_by');
			$table->enum('created_by_user_type', ['Admin', 'Tour Operator', 'Customer', 'Affiliate'])->default('Customer');
			$table->dateTime('updated_at')->nullable();
			$table->unsignedBigInteger('updated_by')->nullable()->index('updated_by');
			$table->enum('updated_by_user_type', ['Admin', 'Tour Operator', 'Affiliate'])->default('Tour Operator');
			$table->dateTime('deleted_at')->nullable();
			$table->unsignedBigInteger('deleted_by')->nullable()->default(0)->index('deleted_by');
			$table->enum('deleted_by_user_type', ['Admin', 'Tour Operator'])->default('Tour Operator');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tour_booking_package_swipe_history');
    }
};
