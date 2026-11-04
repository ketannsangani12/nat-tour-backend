<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCancellationWithinDaysFieldInTourOperatorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tour_operators', function (Blueprint $table) {
            $table->integer('cancellation_within_days')->default(1)->after('cancellation_policy')->comment('Add number of days to consider the partial payment.');
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
            $table->dropColumn('cancellation_within_days');
        });
    }
}
