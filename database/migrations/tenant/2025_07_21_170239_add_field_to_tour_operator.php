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
			$table->string('reseller_id')->nullable()->after('bokun');
            $table->string('reseller_api_key')->nullable()->after('bokun');
		});

		Schema::table('tour_bookings_master', function (Blueprint $table) {
			$table->string('reseller_id')->nullable()->after('get_your_guide_reference_booking');
		});

        Schema::table('tour_operators', function (Blueprint $table) {
			$table->string('supplier_id')->nullable()->after('tripadvisor_reseller_id');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_operators', function (Blueprint $table) {
			$table->dropColumn(['supplier_id']);
        });

		Schema::table('tour_bookings_master', function (Blueprint $table) {
			$table->dropColumn(['reseller_id']);
		});

		Schema::table('customers', function (Blueprint $table) {
			$table->dropColumn(['reseller_id', 'reseller_api_key']);
		});
    }
};
