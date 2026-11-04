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
        Schema::table('gratuity_distributions', function (Blueprint $table) {
            $table->unsignedBigInteger('payroll_schedule_id')->nullable()->after('gratuity_id')->comment('Reference to gratuity_payroll_schedules table');
            $table->enum('status', ['Ready', 'Schedule', 'Paid', 'Error', 'Failed'])->default('Ready');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gratuity_distributions', function (Blueprint $table) {
            $table->dropColumn('payroll_schedule_id');
            $table->dropColumn('status');
        });
    }
};
