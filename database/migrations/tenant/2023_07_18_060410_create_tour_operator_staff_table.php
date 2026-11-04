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
        Schema::create('tour_operator_staff', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tour_operator_id')->nullable()->index('tour_operator_staff_tour_operator_id');
            $table->string('first_name', 50)->nullable()->index();
            $table->string('last_name', 50)->nullable()->index();
            $table->string('email', 50)->nullable()->index('email');
            $table->string('password')->nullable();
            $table->string('phone_number', 30)->nullable();
            $table->enum('status', ['Inactive', 'Active', 'Deleted'])->default('Active')->index('status');
            $table->double('build_no')->default(0);
            $table->rememberToken()->nullable(false);
            $table->dateTime('created_at')->useCurrent()->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index('created_by');
            $table->enum('created_by_user_type', ['Admin', 'Tour Operator'])->default('Tour Operator');
            $table->dateTime('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable()->index('updated_by');
            $table->enum('updated_by_user_type', ['Admin', 'Tour Operator'])->default('Tour Operator');
            $table->dateTime('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable()->default(0)->index('deleted_by');
            $table->enum('deleted_by_user_type', ['Admin', 'Tour Operator'])->default('Tour Operator');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tour_operator_staff');
    }
};
