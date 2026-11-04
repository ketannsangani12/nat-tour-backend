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
        Schema::create('addon_field_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')->constrained('addon_fields')->onDelete('cascade');
            $table->string('label');
            $table->string('value');
            $table->boolean('additional_fee')->default(false);
            $table->decimal('price', 10, 2)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addon_field_options');
    }
};
