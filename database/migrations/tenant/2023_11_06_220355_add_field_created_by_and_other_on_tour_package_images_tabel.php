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
        Schema::table('tour_package_images', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->after('created_at');
            $table->enum('created_by_user_type', ['Admin', 'Tour Operator', 'Customer', 'Affiliate'])->default('Admin')->after('created_by');
            $table->unsignedBigInteger('updated_by')->nullable()->after('updated_at');
            $table->enum('updated_by_user_type', ['Admin', 'Tour Operator', 'Customer', 'Affiliate'])->default('Tour Operator')->after('updated_by');
            $table->unsignedBigInteger('deleted_by')->nullable()->default(0)->after('deleted_at');
            $table->enum('deleted_by_user_type', ['Admin', 'Tour Operator', 'Customer', 'Affiliate'])->default('Tour Operator')->after('deleted_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_package_images', function (Blueprint $table) {
            $table->dropColumn('created_by', 'created_by_user_type', 'updated_by', 'updated_by_user_type', 'deleted_by', 'deleted_by_user_type');
        });
    }
};
