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
        Schema::table('tour_booking_payment_links', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->index()->after('override_status');
            $table->enum('created_by_user_type', ['Admin', 'Tour Operator', 'Affiliate'])->default('Tour Operator')->after('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_booking_payment_links', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->index()->after('override_status');
            $table->enum('created_by_user_type', ['Admin', 'Tour Operator', 'Affiliate'])->default('Tour Operator')->after('created_by');
        });
    }
};
