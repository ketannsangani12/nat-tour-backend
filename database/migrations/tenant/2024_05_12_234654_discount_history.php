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
        Schema::create('discount_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tour_booking_id')->index('tour_booking_id')->nullable()->index('discount_history_tour_booking_id_foreign');
            $table->unsignedBigInteger('old_tour_promotion_id')->index('tour_promotion_id')->nullable()->index('discount_history_tour_promotion_id');
            $table->unsignedBigInteger('new_tour_promotion_id')->index('new_tour_promotion_id')->nullable()->index('discount_history_new_tour_promotion_id');
            $table->float('old_discount2_value')->default(0.0);
            $table->float('new_discount2_value')->default(0.0);
            $table->float('old_discount2_percentage')->default(0.0);
            $table->float('new_discount2_percentage')->default(0.0);
            $table->text('old_discount_promo_code')->nullable();
            $table->text('new_discount_promo_code')->nullable();
            $table->float('service_commission')->default(0.0);
            $table->float('rate')->default(0.0);
            $table->float('old_total')->default(0.0);
            $table->float('new_total')->default(0.0);
            $table->text('discount_type')->nullable();
            $table->text('payment_method')->nullable()->comment('Payment method of the discount');
            $table->dateTime('created_at')->useCurrent()->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index('created_by');
            $table->enum('created_by_user_type', ['Admin', 'Tour Operator', 'Customer', 'Affiliate']);
            $table->dateTime('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable()->index('updated_by')->nullable();
            $table->enum('payment_type', ['Payment', 'Refund']);
            $table->enum('updated_by_user_type', ['Admin', 'Tour Operator', 'Affiliate']);
            $table->dateTime('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable()->default(0)->index('deleted_by');
            $table->enum('deleted_by_user_type', ['Admin', 'Tour Operator'])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discount_history');
    }
};
