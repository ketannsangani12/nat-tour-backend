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
        Schema::create('tour_booking_addon_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tour_booking_addon_id')->nullable()->index();
            $table->string('payment_method')->nullable();
            $table->enum('status', ['paid','open','refunded'])->default('open');
            $table->string('operation')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('application_fee_id')->nullable();
            $table->decimal('field_price', 10, 2)->default(0)->comment('field price of the addon');
            $table->decimal('field_fee', 10, 2)->default(0);
            $table->decimal('option_price', 10, 2)->default(0)->comment('Radio/dropdown field option price');
            $table->decimal('option_fee', 10, 2)->default(0);
            $table->text('notes')->nullable()->comment('Refund/cancellation reason');
            $table->dateTime('operation_at')->nullable()->index();
            $table->unsignedBigInteger('operation_by')->nullable()->default(0);
            $table->enum('operation_by_user_type', ['Admin', 'Tour Operator', 'Customer', 'Affiliate'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tour_booking_addon_transactions');
    }
};
