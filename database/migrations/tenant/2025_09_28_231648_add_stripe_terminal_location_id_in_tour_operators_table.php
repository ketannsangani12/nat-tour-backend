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
        if (!Schema::hasColumn('tour_operators', 'stripe_terminal_location_id')) {
            Schema::table('tour_operators', function (Blueprint $table) {
                $table->string('stripe_terminal_location_id')
                    ->nullable()
                    ->after('stripe_webhook_secret');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('tour_operators', 'stripe_terminal_location_id')) {
            Schema::table('tour_operators', function (Blueprint $table) {
                $table->dropColumn('stripe_terminal_location_id');
            });
        }
    }
};
