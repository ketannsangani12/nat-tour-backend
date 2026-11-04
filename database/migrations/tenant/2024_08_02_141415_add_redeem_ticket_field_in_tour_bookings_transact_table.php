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
        Schema::table('tour_bookings_transact', function (Blueprint $table) {
            $table->text('redeem_ticket')->after('check_in_status')->nullable()->comment('JSON Format: [ { id: $1, name: $package, data: [ { status: $status, dateTime: 0000-00-00 00:00:00 }, { ... } ] }, { ... } ] WHERE $1 will be package_id, $package will be package name and $status can be Active/Inactive');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_bookings_transact', function (Blueprint $table) {
            $table->dropColumn('redeem_ticket');
        });
    }
};
