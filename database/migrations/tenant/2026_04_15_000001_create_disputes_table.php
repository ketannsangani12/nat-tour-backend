<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('stripe_dispute_id', 255)->unique()->index();
            $table->unsignedBigInteger('booking_id')->index();
            $table->unsignedBigInteger('transaction_id')->nullable()->index();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('reason', 50)->index();
            $table->string('status', 50)->index();
            $table->timestamp('evidence_due_by')->nullable()->index();
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('booking_id')->references('id')->on('tour_bookings_master')->onDelete('cascade');
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('set null');
            
            // Composite indexes for common queries
            $table->index(['status', 'evidence_due_by']);
            $table->index(['booking_id', 'status']);
            $table->index(['created_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('disputes');
    }
};