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
        if (Schema::hasTable('webhook_processing_errors')) {
            return;
        }

        Schema::create('webhook_processing_errors', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 50);
            $table->string('stripe_object_id', 255)->nullable();
            $table->text('error_message');
            $table->json('error_data')->nullable();
            $table->timestamps();
            
            $table->index(['event_type', 'created_at']);
            $table->index('stripe_object_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_processing_errors');
    }
};
