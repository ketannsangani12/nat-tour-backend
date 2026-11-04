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
        Schema::create('booking_checkout_terms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tour_booking_id');
            $table->string('term_key', 100);
            $table->text('term_label');
            $table->boolean('is_required')->default(false);
            $table->boolean('customer_response')->default(false);
            $table->timestamps();

            $table->foreign('tour_booking_id')
                ->references('id')
                ->on('tour_bookings_master')
                ->onDelete('cascade');

            $table->index(['tour_booking_id', 'term_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_checkout_terms');
    }
};
