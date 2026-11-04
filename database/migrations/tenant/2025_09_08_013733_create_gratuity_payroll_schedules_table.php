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
        Schema::create('gratuity_payroll_schedules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('start_date')->default(null)->nullable();
            $table->date('end_date')->default(null)->nullable();
            $table->date('payroll_date')->default(null)->nullable();
            $table->text('errors')->nullable();
            $table->enum('status', ['Schedule', 'Paid', 'Error', 'Failed'])->default('Schedule');
            $table->unsignedBigInteger('created_by')->nullable()->default(0);
            $table->enum('created_by_user_type', ['Admin', 'Tour Operator', 'Customer', 'Affiliate'])->default('Admin');
            $table->unsignedBigInteger('updated_by')->nullable()->default(0);
            $table->enum('updated_by_user_type', ['Admin', 'Tour Operator', 'Customer', 'Affiliate'])->default('Admin');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gratuity_payroll_schedules');
    }
};
