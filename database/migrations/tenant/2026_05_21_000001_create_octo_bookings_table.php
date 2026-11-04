<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('octo_bookings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('affiliate_id');
            $table->unsignedBigInteger('tour_slot_id');
            $table->unsignedBigInteger('tour_package_id')->nullable();
            $table->unsignedBigInteger('tour_booking_id')->nullable();
            $table->enum('status', ['ON_HOLD', 'CONFIRMED', 'CANCELLED', 'EXPIRED'])->default('ON_HOLD')->index();
            $table->unsignedSmallInteger('tourists')->default(0);
            $table->unsignedSmallInteger('infants')->default(0);
            $table->json('unit_items');
            $table->json('contact')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('expire_at');
            $table->dateTime('confirmed_at')->nullable();
            $table->timestamps();

            $table->index('tour_booking_id');
            $table->index('tour_slot_id');
            $table->index('affiliate_id');
            $table->index(
                ['affiliate_id', 'tour_slot_id', 'status', 'expire_at'],
                'octo_bookings_hold_lookup'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('octo_bookings');
    }
};
