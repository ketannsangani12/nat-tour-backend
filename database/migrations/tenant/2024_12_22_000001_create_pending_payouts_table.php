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
        Schema::create('pending_payouts', function (Blueprint $table) {
            $table->id();
            $table->string('payout_reference')->unique();
            $table->unsignedBigInteger('rule_id');
            $table->string('stripe_account_id');
            $table->decimal('total_amount', 10, 2);
            $table->decimal('platform_fee', 10, 2);
            $table->decimal('net_amount', 10, 2);
            $table->integer('booking_count');
            $table->enum('status', ['pending_approval', 'approved', 'rejected'])->default('pending_approval');
            $table->json('rule_snapshot');
            $table->json('calculation_data');
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->unsignedInteger('rejected_by')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('rule_id')->references('id')->on('auto_payout_rules')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('admins')->onDelete('set null');
            $table->foreign('rejected_by')->references('id')->on('admins')->onDelete('set null');

            // Indexes for performance
            $table->index('status');
            $table->index('stripe_account_id');
            $table->index('created_at');
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_payouts');
    }
};