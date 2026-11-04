<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $foreignKey = 'taxes_created_by';
        $tableName = 'taxes';

        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Check if the foreign key exists
        $exists = DB::selectOne("
            SELECT CONSTRAINT_NAME 
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
            WHERE TABLE_NAME = ? 
              AND CONSTRAINT_NAME = ?
              AND TABLE_SCHEMA = DATABASE()
        ", [$tableName, $foreignKey]);

        if ($exists) {
            DB::statement("ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$foreignKey}`");
        }

        // Enable foreign key checks again
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Add new columns
        Schema::table($tableName, function (Blueprint $table) {
            $table->enum('created_by_user_type', ['Admin', 'Tour Operator', 'Affiliate'])->default('Admin')->after('created_by');
            $table->enum('updated_by_user_type', ['Admin', 'Tour Operator', 'Affiliate'])->default('Admin')->after('updated_by');
            $table->enum('deleted_by_user_type', ['Admin', 'Tour Operator', 'Affiliate'])->nullable()->after('deleted_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('taxes', function (Blueprint $table) {
            $table->dropColumn(['created_by_user_type', 'updated_by_user_type', 'deleted_by_user_type']);
        });
    }
};
