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
        Schema::create('cancellation_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('package_id')->index();
            $table->text('title')->nullable();
            $table->longText('description')->nullable();
            $table->integer('days_hours_before')->default(0);
            $table->enum('type', ['days', 'hours'])->default('days');
            $table->enum('refund_basis', ['ticket_cost', 'full_amount'])->default('ticket_cost');
            $table->integer('refund_percent')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cancellation_policies');
    }
};
