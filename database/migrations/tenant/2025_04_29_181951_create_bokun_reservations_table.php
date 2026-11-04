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
        Schema::create('bokun_reservations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('product_code')->nullable();
            $table->string('reservation_code')->nullable();
            $table->date('date')->nullable()->index();
            $table->time('time')->nullable()->index();
            $table->integer('seats')->default(0);
            $table->dateTime('expired_at')->nullable();
            $table->timestamps();

            $table->index(['date', 'time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bokun_reservations');
    }
};
