<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tour_operator_staff_roles', function (Blueprint $table) {
            $table->foreign(['role_id'], 'tour_operator_staff_roles_role_id')->references(['id'])->on('roles')->onUpdate('NO ACTION')->onDelete('NO ACTION');
            $table->foreign(['tour_operator_staff_id'], 'tour_operator_staff_roles_tour_operator_staff_id')->references(['id'])->on('tour_operator_staff')->onUpdate('NO ACTION')->onDelete('NO ACTION');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tour_operator_staff_roles', function (Blueprint $table) {
            $table->dropForeign('tour_operator_staff_roles_role_id');
            $table->dropForeign('tour_operator_staff_roles_tour_operator_staff_id');
        });
    }
};
