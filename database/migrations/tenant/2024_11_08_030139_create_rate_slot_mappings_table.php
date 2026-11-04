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
        Schema::create('rate_slot_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tour_package_id');
            $table->unsignedBigInteger('affiliate_id')->nullable();
            $table->unsignedBigInteger('custom_date_id');
            $table->string('time_from');
            $table->string('time_to')->nullable();
            $table->timestamps();

            // Foreign key constraints
            // $table->foreign('tour_package_rate_id')->references('id')->on('tour_package_rates')->onDelete('cascade');
            // $table->foreign('custom_date_id')->references('id')->on('surcharge_dates')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rate_slot_mappings');
    }
};
