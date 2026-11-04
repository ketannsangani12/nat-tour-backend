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
        Schema::create('webhook_audit', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique()->comment('Stripe event ID for deduplication');
            $table->string('event_type', 50)->comment('Type of webhook event (e.g., charge.dispute.created)');
            $table->string('object_id')->nullable()->comment('ID of the Stripe object (dispute, payment_intent, etc.)');
            $table->json('payload')->comment('Complete webhook payload from Stripe');
            $table->string('signature', 500)->comment('Stripe webhook signature for verification');
            $table->enum('processing_status', ['pending', 'processed', 'failed', 'skipped'])->default('pending');
            $table->text('processing_notes')->nullable()->comment('Notes about processing status or errors');
            $table->timestamp('received_at')->comment('When the webhook was received');
            $table->timestamp('processed_at')->nullable()->comment('When the webhook was processed');
            $table->string('tenant_id', 50)->comment('Tenant that received this webhook');
            $table->string('ip_address', 45)->nullable()->comment('IP address of webhook sender');
            $table->text('user_agent')->nullable()->comment('User agent of webhook sender');
            $table->timestamps();

            // Indexes for performance
            $table->index(['event_type', 'received_at']);
            $table->index(['object_id', 'event_type']);
            $table->index(['processing_status', 'received_at']);
            $table->index(['tenant_id', 'received_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_audit');
    }
};