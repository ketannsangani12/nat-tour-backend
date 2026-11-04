<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->unsignedInteger('stripe_dispute_fee')->nullable()
                ->after('reversed_amount')
                ->comment('Dispute fee in cents charged by Stripe (from balance_transactions)');
        });
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropColumn('stripe_dispute_fee');
        });
    }
};
