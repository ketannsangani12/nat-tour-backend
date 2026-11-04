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
        if (Schema::hasTable('admins')) {
            return;
        }

        Schema::create('admins', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 50)->nullable();
            $table->string('email', 50)->nullable();
            $table->string('password')->nullable();
            $table->integer('department_id')->nullable();
            $table->text('profile_photo_path')->nullable();
            $table->enum('status', ['Inactive', 'Active'])->default('Active');
            $table->string('ip_address')->nullable();
            $table->string('login_status');
            $table->rememberToken()->nullable(false);
            $table->dateTime('created_at')->useCurrent()->nullable();
            $table->unsignedInteger('created_by')->nullable()->index('created_by');
            $table->dateTime('updated_at')->nullable();
            $table->unsignedInteger('updated_by')->nullable()->index('updated_by');
            $table->dateTime('deleted_at')->nullable();
            $table->unsignedInteger('deleted_by')->nullable()->default(0)->index('deleted_by');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('admins');
    }
};
