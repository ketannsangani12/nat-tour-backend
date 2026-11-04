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
        Schema::table('tour_operators', function (Blueprint $table) {
            $table->string('stripe_webhook_secret')->after('cancellation_within_days')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_operators', function (Blueprint $table) {
            $table->dropColumn('stripe_webhook_secret');
        });
    }
};
