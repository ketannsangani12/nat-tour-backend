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
        Schema::create('tour_operator_email_templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tour_operator_id');
            $table->unsignedBigInteger('email_template_id');
            $table->string('subject')->index();
            $table->text('body');
            $table->text('params')->nullable();
            $table->string('created_by', 30)->nullable();
            $table->string('created_from', 100)->nullable();
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
        Schema::dropIfExists('tour_operator_email_templates');
    }
};
