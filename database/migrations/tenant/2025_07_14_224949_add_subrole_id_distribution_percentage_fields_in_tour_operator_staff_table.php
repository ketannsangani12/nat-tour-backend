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
        Schema::table('tour_operator_staff', function (Blueprint $table) {
            $table->unsignedBigInteger('role_type_id')->nullable()->foreign('role_type_id')->references('id')->on('role_types')->onDelete('cascade')->after('capacity');
            $table->decimal('tip_distribution_percentage', 10, 2)->default(0.00)->after('role_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_operator_staff', function (Blueprint $table) {
            $table->dropForeign(['role_type_id']);
            $table->dropColumn('role_type_id');
            $table->dropColumn('tip_distribution_percentage');
        });
    }
};
