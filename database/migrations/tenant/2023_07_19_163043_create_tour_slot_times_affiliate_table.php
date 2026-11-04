<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTourSlotTimesAffiliateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('tour_slot_times_affiliate')){
            Schema::create('tour_slot_times_affiliate', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tour_package_id')->nullable()->index();
                $table->foreign('tour_package_id')->references('id')->on('tour_packages')->onDelete('cascade');
                $table->time('time_from')->nullable();
                $table->time('time_to')->nullable();
                $table->enum('status', [ 'Active', 'Inactive' ])->default('Active');
                $table->dateTime('created_at')->useCurrent()->nullable()->index();
                $table->unsignedBigInteger('created_by')->default(0)->index();
                $table->enum('created_by_user_type', [ 'Admin', 'Tour Operator' ])->default('Tour Operator');
                $table->dateTime('updated_at')->nullable();
                $table->unsignedBigInteger('updated_by')->default(0)->index();
                $table->enum('updated_by_user_type', [ 'Admin', 'Tour Operator' ])->default('Tour Operator');
                $table->dateTime('deleted_at')->nullable();
                $table->unsignedBigInteger('deleted_by')->default(0)->index();
                $table->enum('deleted_by_user_type', [ 'Admin', 'Tour Operator' ])->default('Tour Operator');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tour_slot_times_affiliate');
    }
}
