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
        Schema::create('gratuities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('booking_id')->foreign('booking_id')->references('id')->on('tour_bookings_master')->onDelete('cascade');
            $table->unsignedBigInteger('gratuity_setting_id')->foreign('gratuity_setting_id')->references('id')->on('gratuity_settings')->onDelete('cascade');
            $table->unsignedBigInteger('customer_id')->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->enum('gratuity_type', ['pre', 'post'])->default('pre');
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->decimal('fees', 10, 2)->default(0.00);
            $table->string('payment_method',50)->nullable();
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->string('message')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->default(0);
            $table->enum('created_by_user_type', ['Admin', 'Tour Operator', 'Customer', 'Affiliate'])->default('Admin');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gratuities');
    }
};
