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
            $table->decimal('service_commission_percentage', 10, 8)->nullable()->default(0)->after('long_description');
            $table->decimal('stripe_commission_percentage', 10, 8)->nullable()->default(0)->after('service_commission_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_packages', function (Blueprint $table) {
            $table->dropColumn('service_commission_percentage', 'stripe_commission_percentage');
        });
    }
};
