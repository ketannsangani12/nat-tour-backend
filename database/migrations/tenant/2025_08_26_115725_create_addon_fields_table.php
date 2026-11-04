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
        Schema::create('addon_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('addon_forms')->onDelete('cascade');
            $table->foreignId('parent_field_id')->nullable()->constrained('addon_fields')->onDelete('cascade');
            $table->string('label');
            $table->enum('type', ['text', 'textbox', 'number', 'checkbox', 'radio', 'dropdown'])->nullable();
            $table->text('description')->nullable();
            $table->boolean('required')->default(false);
            $table->enum('unit_type', ['one_time', 'price_per_unit', 'price_per_pax'])->nullable();
            $table->enum('visibility', ['both', 'frontend', 'backend'])->nullable();
            $table->enum('status', ['Active', 'Inactive'])->nullable();
            $table->boolean('additional_fee')->default(false);
            $table->decimal('price', 10, 2)->nullable();
            $table->integer('min')->nullable();
            $table->integer('max')->nullable();
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
        Schema::dropIfExists('addon_fields');
    }
};
