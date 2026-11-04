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
        Schema::create('payouts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tour_booking_id')->nullable()->index('payouts_tour_booking_id_foreign');
            $table->unsignedBigInteger('transaction_id')->nullable()->index('payouts_transaction_id_foreign');
            $table->unsignedBigInteger('package_id')->nullable()->index('payouts_package_id_foreign');
            $table->string('stripe_account_id', 191)->nullable()->comment('Third-party Stripe Account ID');
            $table->string('transfer_id', 191)->nullable()->comment('Stripe transfer transaction reference');
            $table->decimal('total_amount', 7, 2)->nullable()->default(0.00);
            $table->decimal('commission_percentage', 5, 2)->nullable()->default(0.00);
            $table->decimal('commission_amount', 7, 2)->nullable()->default(0.00);
            $table->decimal('payout_amount', 7, 2)->nullable()->default(0.00);
            $table->string('payment_method', 100)->nullable()->index();
            $table->string('charge_type', 191)->nullable();
            $table->enum('payout_status', ['Pending', 'Completed', 'Failed', 'Reversed'])->default('Completed');
            $table->dateTime('created_at')->useCurrent()->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index('created_by');
            $table->enum('created_by_user_type', ['Admin', 'Tour Operator', 'Customer', 'Affiliate'])->default('Admin');
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
