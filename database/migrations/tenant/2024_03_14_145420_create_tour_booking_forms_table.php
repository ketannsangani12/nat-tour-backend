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
        Schema::create('tour_booking_forms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tour_package_form_id');
            $table->unsignedBigInteger('tour_booking_id');
            $table->json('form_submission');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tour_booking_forms');
    }
};
