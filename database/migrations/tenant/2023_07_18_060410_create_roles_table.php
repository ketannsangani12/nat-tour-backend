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
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 30)->nullable();
            $table->unsignedTinyInteger('display_order')->nullable();
            $table->enum('status', ['Inactive', 'Active'])->default('Active')->index('status');
            $table->dateTime('created_at')->useCurrent()->nullable();
            $table->unsignedInteger('created_by')->nullable()->index('roles_created_by');
            $table->dateTime('updated_at')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->unsignedInteger('deleted_by')->nullable()->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('roles');
    }
};
