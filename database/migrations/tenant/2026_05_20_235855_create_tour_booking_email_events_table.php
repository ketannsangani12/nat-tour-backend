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
        if (Schema::hasTable('tour_booking_email_events')) {
            return;
        }

        Schema::create('tour_booking_email_events', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('tour_booking_id')->nullable();
            $table->unsignedBigInteger('tour_booking_history_id')->nullable();
            $table->unsignedInteger('sent_email_id')->nullable();
            $table->enum('status', ['Sent','Delivered','Clicked','Bounce','Open','Complaint','Reject','Unsubscribe','Failed'])->default('Sent');
            $table->integer('opens')->nullable();
            $table->integer('clicks')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index('tour_booking_id');
            $table->index('tour_booking_history_id');
            $table->index('sent_email_id');
            $table->index('status');

            $table->foreign('tour_booking_id')->references('id')->on('tour_bookings_master')->onUpdate('NO ACTION')->onDelete('NO ACTION');
            $table->foreign('tour_booking_history_id')->references('id')->on('tour_booking_history')->onUpdate('NO ACTION')->onDelete('NO ACTION');
            $table->foreign('sent_email_id')->references('id')->on('sent_emails')->onUpdate('NO ACTION')->onDelete('NO ACTION');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tour_booking_email_events');
    }
};
