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
        Schema::table('tour_package_images', function (Blueprint $table) {
            $table->foreign(['tour_package_id'])->references(['id'])->on('tour_packages')->onUpdate('NO ACTION')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tour_package_images', function (Blueprint $table) {
            $table->dropForeign('tour_package_images_tour_package_id_foreign');
        });
    }
};
