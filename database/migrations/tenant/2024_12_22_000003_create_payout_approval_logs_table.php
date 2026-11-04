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
        Schema::create('payout_approval_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pending_payout_id');
            $table->enum('action', ['created', 'approved', 'rejected', 'executed', 'failed']);
            $table->unsignedInteger('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Foreign key constraints
            $table->foreign('pending_payout_id')->references('id')->on('pending_payouts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('admins')->onDelete('set null');

            // Indexes for performance
            $table->index('pending_payout_id');
            $table->index('action');
            $table->index('created_at');
            $table->index(['pending_payout_id', 'action']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payout_approval_logs');
    }
};