<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dispute_audit_trail')) {
            return;
        }

        Schema::create('dispute_audit_trail', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 50)->index();
            $table->json('event_data');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('user_role', 50)->nullable();
            $table->unsignedBigInteger('dispute_id')->nullable()->index();
            $table->string('stripe_dispute_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->string('session_id')->nullable();
            $table->string('tenant_id', 50)->nullable()->index();
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->default('low')->index();
            $table->json('pci_flags')->nullable();
            $table->boolean('requires_investigation')->default(false)->index();
            $table->string('compliance_status', 20)->default('compliant');
            $table->json('security_flags')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['risk_level', 'created_at']);
            $table->index(['requires_investigation', 'created_at']);
            $table->index(['tenant_id', 'event_type', 'created_at']);
            $table->index(['user_id', 'event_type', 'created_at']);
            $table->index(['dispute_id', 'event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_audit_trail');
    }
};