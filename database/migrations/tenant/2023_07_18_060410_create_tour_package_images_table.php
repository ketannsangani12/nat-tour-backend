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
        Schema::create('tour_package_images', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tour_package_id')->nullable()->index();
            $table->string('image', 191)->nullable();
            $table->text('url')->nullable();
            $table->integer('is_cover')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tour_package_images');
    }
};
