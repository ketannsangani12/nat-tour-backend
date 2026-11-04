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
        Schema::table('tour_operators', function (Blueprint $table) {
            $table->foreign(['city_id'], 'tour_operators_city_id')->references(['id'])->on('cities')->onUpdate('NO ACTION')->onDelete('NO ACTION');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tour_operators', function (Blueprint $table) {
            $table->dropForeign('tour_operators_city_id');
        });
    }
};
