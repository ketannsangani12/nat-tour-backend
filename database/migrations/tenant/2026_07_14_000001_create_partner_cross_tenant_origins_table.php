<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mapping of a local (partner-tenant, e.g. "lc") package to the origin package/s
 * it is assembled from, each of which lives in ANOTHER tenant.
 *
 *   - ONE row for a package  => single-package cross-tenant passthrough resell
 *   - TWO OR MORE rows       => a combo assembled from several origins
 *
 * time_from / time_to mirror the origin's tour_slot_times columns: the engine
 * matches each origin's bookable slot on the booking date at this time_from.
 * All origins book on the SAME date as the local booking. buffer_time_minutes is
 * a guest-facing reference only (spare time between activities in a combo); it is
 * not used in availability or booking logic and is irrelevant for a single row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_cross_tenant_origins', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('package_id')->index();            // local (lc) package
            $table->unsignedBigInteger('origin_package_id');
            $table->string('origin_tenant');
            $table->unsignedBigInteger('origin_customer_id')->nullable(); // standard pricing / attribution
            $table->time('time_from');
            $table->time('time_to')->nullable();
            $table->unsignedInteger('buffer_time_minutes')->default(0);   // guest-facing reference only
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_cross_tenant_origins');
        // Schema::table('tour_packages', function (Blueprint $table) {
        //     $table->dropColumn('cross_tenant_origins');
        // });
    }
};
