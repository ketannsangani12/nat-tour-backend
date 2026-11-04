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
        Schema::table('surcharge_dates', function (Blueprint $table) {
            // Timestamp details
            $table->unsignedBigInteger('created_by')->nullable()->index('created_by')->after('created_at');
            $table->enum('created_by_user_type', ['Admin', 'Tour Operator', 'Customer', 'Affiliate'])->default('Admin')->after('created_by');
            $table->unsignedBigInteger('updated_by')->nullable()->index('updated_by')->after('updated_at');
            $table->enum('updated_by_user_type', ['Admin', 'Tour Operator', 'Customer', 'Affiliate'])->default('Tour Operator')->after('updated_by');
            $table->dateTime('deleted_at')->nullable()->after('updated_by_user_type');
            $table->unsignedBigInteger('deleted_by')->nullable()->default(0)->index('deleted_by')->after('deleted_at');
            $table->enum('deleted_by_user_type', ['Admin', 'Tour Operator', 'Customer', 'Affiliate'])->default('Tour Operator')->after('deleted_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surcharge_dates', function (Blueprint $table) {
            $table->dropColumn(['created_by', 'created_by_user_type', 'updated_by', 'updated_by_user_type', 'deleted_at', 'deleted_by', 'deleted_by_user_type']);
        });
    }
};
