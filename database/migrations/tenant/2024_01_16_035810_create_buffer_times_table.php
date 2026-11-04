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
        Schema::create('buffer_times', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('from_package_id')->index('from_package_id')->comment('From package ID, e.g. A');
            $table->unsignedBigInteger('to_package_id')->index('to_package_id')->comment('To package ID, e.g. B ');
            $table->time('travel_duration')->nullable()->index('travel_duration')->comment('To add the duration from package A to B');
            $table->integer('sequence')->nullable()->comment('To order by the packages');
            $table->integer('group')->default(1)->comment('To group the packages');
            $table->enum('status', ['Inactive', 'Active'])->default('Active');
            $table->dateTime('created_at')->useCurrent()->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index('created_by');
            $table->enum('created_by_user_type', ['Admin', 'Tour Operator', 'Affiliate', 'Customer'])->default('Tour Operator');
            $table->dateTime('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable()->index('updated_by');
            $table->enum('updated_by_user_type', ['Admin', 'Tour Operator', 'Affiliate', 'Customer'])->default('Tour Operator');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buffer_times');
    }
};
