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
        Schema::create('tour_package_photos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tour_package_id')->nullable()->index('tour_package_photos_tour_package_id');
            $table->unsignedTinyInteger('placement')->default(1)->comment('1 = Header, 2 - Beside Description');
            $table->text('path');
            $table->enum('status', ['Inactive', 'Active'])->default('Active')->index('status');
            $table->dateTime('created_at')->useCurrent()->nullable();
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
        Schema::dropIfExists('tour_package_photos');
    }
};
