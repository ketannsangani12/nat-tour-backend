<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->string('stripe_reversal_id')->nullable()->after('stripe_dispute_id');
            $table->unsignedInteger('reversed_amount')->nullable()->after('stripe_reversal_id')
                  ->comment('Transfer reversal amount in cents');
        });
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropColumn(['stripe_reversal_id', 'reversed_amount']);
        });
    }
};
