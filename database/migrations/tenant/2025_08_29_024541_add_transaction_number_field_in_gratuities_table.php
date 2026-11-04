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
        Schema::table('gratuities', function (Blueprint $table) {
            $table->string('transaction_number', 50)->default(null)->nullable()->after('fees');
            $table->string('application_fee_id')->default(null)->nullable()->after('transaction_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gratuities', function (Blueprint $table) {
            $table->dropColumn('transaction_number');
            $table->dropColumn('application_fee_id');
        });
    }
};
