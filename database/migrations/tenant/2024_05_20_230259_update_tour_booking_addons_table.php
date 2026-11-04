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
        Schema::table('tour_booking_addons', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('id');
            $table->float('price')->default(0.0)->after('name');
            $table->string('application_fee_id')->nullable()->after('transaction_id');
            $table->string('payment_method')->nullable()->after('description');
            $table->unsignedBigInteger('operation_by')->after('image');
            $table->string('operation_by_user_type')->after('operation_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_booking_addons', function (Blueprint $table) {
            $table->dropColumn(['parent_id', 'price', 'application_fee_id', 'payment_method', 'operation_by', 'operation_by_user_type']);
        });
    }
};
