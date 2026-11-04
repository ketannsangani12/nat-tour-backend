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
        if (Schema::hasTable('auto_payout_rules')) {
            return;
        }

        Schema::create('auto_payout_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'yearly'])->default('weekly');
            $table->integer('day_of_week')->nullable()->comment('0 = Sunday, 6 = Saturday');
            $table->integer('day_of_month')->nullable()->comment('1 to 31, depending on the month');
            $table->time('execution_time')->default('09:00:00');
            $table->decimal('minimum_amount', 10, 2)->default(0);
            $table->boolean('enabled')->default(true);
            $table->dateTime('last_executed_at')->nullable();
            $table->dateTime('next_execution_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_payout_rules');
    }
};
