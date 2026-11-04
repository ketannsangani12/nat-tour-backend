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
        Schema::table('tour_operator_email_templates', function (Blueprint $table) {
            $table->renameColumn('tour_operator_id', 'tour_package_id');
        });

        Schema::rename('tour_operator_email_templates', 'tour_package_email_templates');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_package_email_templates', function (Blueprint $table) {
            $table->renameColumn('tour_package_id', 'tour_operator_id');
        });

        Schema::rename('tour_package_email_templates', 'tour_operator_email_templates');
    }
};
