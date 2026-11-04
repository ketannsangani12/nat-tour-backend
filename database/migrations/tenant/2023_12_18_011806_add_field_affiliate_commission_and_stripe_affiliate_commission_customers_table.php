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
            $table->decimal('affiliate_processing_percentage', 10, 8)->nullable()->default(0)->after('is_billable');
            $table->decimal('stripe_affiliate_percentage', 10, 8)->nullable()->default(0)->after('affiliate_processing_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('affiliate_processing_percentage', 'stripe_affiliate_percentage');
        });
    }
};
