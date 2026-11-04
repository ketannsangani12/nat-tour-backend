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
        if (Schema::hasTable('auto_payouts')) {
            return;
        }

        Schema::create('auto_payouts', function (Blueprint $table) {
            $table->id();
            $table->string('payout_reference')->unique();
            $table->unsignedBigInteger('rule_id');
            $table->foreign('rule_id')->references('id')->on('auto_payout_rules')->onDelete('cascade');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('platform_fee', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2)->default(0);
            $table->integer('booking_count')->default(0);
            $table->timestamp('processed_date')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_payouts');
    }
};
