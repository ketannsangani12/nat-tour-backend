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
        Schema::table('tour_packages', function (Blueprint $table) {
            $table->decimal('next_year_service_commission_percentage', 10, 8)->default(0)->after('stripe_commission_percentage');
            $table->decimal('next_year_stripe_commission_percentage', 10, 8)->default(0)->after('next_year_service_commission_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_packages', function (Blueprint $table) {
            $table->dropColumn('next_year_service_commission_percentage', 'next_year_stripe_commission_percentage');
        });
    }
};
