<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAffiliateSlotTimesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('affiliate_slot_times', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tour_slot_time_id')->nullable()->index();
            $table->foreign('tour_slot_time_id')->references('id')->on('tour_slot_times_affiliate')->onDelete('cascade');
            $table->unsignedBigInteger('affiliate_id')->nullable()->index();
            $table->foreign('affiliate_id')->references('id')->on('customers')->onDelete('cascade');
            $table->integer('seat_limit')->nullable();
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

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('affiliate_slot_times');
    }
}
