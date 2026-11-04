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
        Schema::create('global_rate_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tour_package_id');
            $table->unsignedBigInteger('affiliate_id')->nullable();
            $table->decimal('charge_amount', 10, 2)->default(0); // Added precision for decimals
            $table->enum('charge_type', ['upcharge', 'downcharge', 'flat'])->default('upcharge'); // ENUM type for predefined charge types
            $table->enum('charge_mode', ['percentage', 'fixed'])->default('percentage'); // ENUM type for predefined charge modes
            $table->dateTime('date_time'); // Using dateTime instead of string for proper timestamp handling
            $table->string('scheduling_year');
            $table->enum('status', ['Inactive', 'Active', 'Executed'])->default('Active');

            // Timestamp details
            $table->dateTime('created_at')->useCurrent()->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index('created_by');
            $table->enum('created_by_user_type', ['Admin', 'Tour Operator', 'Customer', 'Affiliate'])->default('Admin');
            $table->dateTime('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable()->index('updated_by');
            $table->enum('updated_by_user_type', ['Admin', 'Tour Operator', 'Customer', 'Affiliate'])->default('Tour Operator');
            $table->dateTime('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable()->default(0)->index('deleted_by');
            $table->enum('deleted_by_user_type', ['Admin', 'Tour Operator', 'Customer', 'Affiliate'])->default('Tour Operator');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('global_rate_schedules');
    }
};
