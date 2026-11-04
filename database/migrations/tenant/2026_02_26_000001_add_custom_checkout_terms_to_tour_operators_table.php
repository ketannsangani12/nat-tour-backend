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
            $table->json('custom_checkout_terms')->nullable()->after('stripe_terminal_location_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_operators', function (Blueprint $table) {
            $table->dropColumn('custom_checkout_terms');
        });
    }
};
