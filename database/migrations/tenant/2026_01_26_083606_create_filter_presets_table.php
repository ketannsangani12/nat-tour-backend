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
        Schema::create('filter_presets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('report_type', 50)->default('payment_breakdown');
            $table->json('filters');
            $table->integer('user_id');
            $table->enum('user_type', ['Admin', 'Tour Operator', 'Affiliate'])->default('Admin');
            $table->timestamps();
            
            // Composite index for fast preset retrieval
            $table->index(['user_id', 'user_type', 'report_type'], 'idx_user_report');
            
            // Unique constraint to prevent duplicate names per user
            $table->unique(['user_id', 'user_type', 'report_type', 'name'], 'unique_user_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('filter_presets');
    }
};
