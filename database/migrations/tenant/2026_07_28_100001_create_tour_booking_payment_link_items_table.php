<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_booking_payment_link_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('payment_link_id')->index();
            $table->string('type', 30);
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('payment_link_id')
                  ->references('id')
                  ->on('tour_booking_payment_links')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_booking_payment_link_items');
    }
};
