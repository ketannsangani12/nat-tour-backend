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
        Schema::create('gratuity_distributions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('booking_id')->foreign('booking_id')->references('id')->on('tour_bookings_master')->onDelete('cascade');
            $table->unsignedBigInteger('gratuity_id')->foreign('gratuity_id')->references('id')->on('gratuities')->onDelete('cascade');
            $table->unsignedBigInteger('guide_id')->foreign('guide_id')->references('id')->on('tour_operator_staff')->onDelete('cascade');
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->string('stripe_account_id', 191)->nullable()->comment('Third-party Stripe Account ID');
            $table->string('transfer_id', 191)->nullable()->comment('Stripe transfer transaction reference');
            $table->boolean('is_transferred')->default(false);
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
        Schema::dropIfExists('gratuity_distributions');
    }
};
