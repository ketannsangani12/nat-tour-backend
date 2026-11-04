<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a Partner API cross-tenant booking (identified by the octo_bookings uuid it
 * reuses for the local hold) to the bookings it created in each origin tenant.
 *
 * origin_bookings shape:
 *   [{"tenant":"acme","package_id":12,"tour_slot_id":88,"tour_booking_id":901,"tourists":2,"infants":0,"status":"Booked"}, ...]
 *
 * OCTO is not modified; this partner-owned table holds the cross-tenant linkage
 * that OCTO's own tables have no place for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_cross_tenant_bookings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('booking_uuid')->index();
            $table->unsignedBigInteger('tour_package_id')->nullable();
            $table->json('origin_bookings')->nullable();
            $table->enum('status', ['CONFIRMED', 'CANCELLED', 'FAILED'])->default('CONFIRMED')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_cross_tenant_bookings');
    }
};
