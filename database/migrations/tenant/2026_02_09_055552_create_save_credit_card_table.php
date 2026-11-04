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
        Schema::create('save_credit_card', function (Blueprint $table) {
            $table->id();
            $table->string('tour_booking_id');
            $table->string('customer_id');
            $table->string('payment_method_id');
            $table->string('email');
            $table->string('brand')->nullable();
            $table->string('last4')->nullable();
            $table->string('exp_month')->nullable();
            $table->string('exp_year')->nullable();
            $table->enum('request_from',['FRONTEND', 'BACKEND', 'PAYMENT LINK'])->default('FRONTEND');
            $table->integer('created_by');
            $table->enum('created_by_user_type', ['Admin', 'Tour Operator', 'Affiliate', 'Customer']);
            $table->timestamps();
            $table->integer('deleted_by')->default(0);
            $table->enum('deleted_by_user_type', ['Admin', 'Tour Operator', 'Affiliate', 'Customer'])->nullable();;
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('save_credit_card');
    }
};
