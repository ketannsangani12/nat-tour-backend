<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_event_id')->unique();
            $table->string('event_type');
            $table->string('tenant_id')->nullable()->index();
            $table->json('payload');
            $table->enum('status', ['received', 'dispatched', 'unroutable', 'failed'])->default('received');
            $table->string('error_message')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
