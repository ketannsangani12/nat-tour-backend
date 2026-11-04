<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_booking_addon_transactions', function (Blueprint $table) {
            $table->uuid('operation_group_id')->nullable()->index()->after('tour_booking_addon_id')
                ->comment('Groups all transaction records created in the same edit operation (charge + refund net settlement)');
        });
    }

    public function down(): void
    {
        Schema::table('tour_booking_addon_transactions', function (Blueprint $table) {
            $table->dropIndex(['operation_group_id']);
            $table->dropColumn('operation_group_id');
        });
    }
};
