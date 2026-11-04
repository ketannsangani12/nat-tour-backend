<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * local_time_from groups origins into departures for a multi-departure combo.
 *
 * When set, an origin row belongs to the local (lc) departure at this slot time:
 * the lc slot a customer books (its own time_from) selects the origin rows whose
 * local_time_from matches, and only those are checked for availability / booked.
 * NULL = the origin applies to every lc slot (a single-departure combo — the
 * original behaviour).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_cross_tenant_origins', function (Blueprint $table) {
            $table->time('local_time_from')->nullable()->after('origin_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('partner_cross_tenant_origins', function (Blueprint $table) {
            $table->dropColumn('local_time_from');
        });
    }
};
