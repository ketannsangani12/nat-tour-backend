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
        Schema::create('addon_field_rule_children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('addon_field_rules')->onDelete('cascade');
            $table->foreignId('child_field_id')->constrained('addon_fields')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addon_field_rule_children');
    }
};
