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
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('next_year_affiliate_processing_percentage', 10, 8)->default(0)->after('stripe_affiliate_percentage');
            $table->decimal('next_year_stripe_affiliate_percentage', 10, 8)->default(0)->after('next_year_affiliate_processing_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('next_year_affiliate_processing_percentage', 'next_year_stripe_affiliate_percentage');
        });
    }
};
