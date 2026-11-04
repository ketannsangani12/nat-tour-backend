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
        $foreignKey = 'tour_operator_staff_roles_role_id';
        $tableName = 'tour_operator_staff_roles';

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

        // Proceed with any other changes if needed
        Schema::table($tableName, function (Blueprint $table) {
            // Define new columns or changes here
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // You can recreate the foreign key if needed
        Schema::table('tour_operator_staff_roles', function (Blueprint $table) {
            $table->foreign('role_id')
                  ->references('id')
                  ->on('roles')
                  ->onDelete('cascade');
        });
    }
};
