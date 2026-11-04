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
        Schema::create('tour_booking_payment_links', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->decimal('amount', 10, 2)->default(0);
            $table->text('reason_of_payment')->nullable();
            $table->string('email', 50)->nullable();
            $table->string('phone_number', 30)->nullable();
            $table->string('token', 100)->unique()->nullable();
            $table->string('stripe_session_id')->nullable();
            $table->text('stripe_checkout_url')->nullable();
            $table->enum('status', ['Pending', 'Paid', 'Failed', 'Expired'])->default('Pending')->index();
            $table->dateTime('expired_at')->nullable();
            $table->timestamps(); // created_at & updated_at, indexed automatically if needed
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tour_booking_payment_links');
    }
};
