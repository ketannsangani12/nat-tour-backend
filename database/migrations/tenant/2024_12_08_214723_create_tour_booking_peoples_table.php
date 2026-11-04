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
        Schema::create('tour_booking_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tour_booking_id');
            $table->unsignedBigInteger('tour_booking_transact_id');
            $table->string('name')->nullable();
            $table->integer('age')->default(0);
            $table->decimal('weight', 5, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tour_booking_contacts');
    }
};
