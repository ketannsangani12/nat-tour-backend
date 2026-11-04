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
        Schema::create('addon_field_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')->constrained('addon_fields')->onDelete('cascade');
            $table->foreignId('option_id')->nullable()->constrained('addon_field_options')->onDelete('cascade');
            $table->enum('operator', ['equals', 'not_equals', 'gt', 'gte', 'lt', 'lte', 'checked', 'not_checked']);
            $table->string('value')->nullable(); // For dropdown/radio/text/number
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addon_field_rules');
    }
};
