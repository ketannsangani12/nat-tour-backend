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
        Schema::create('tour_booking_tips', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tour_booking_id')->nullable();
            $table->unsignedBigInteger('tour_operator_staff_id')->nullable();
            $table->decimal('amount', 7)->default(0);
            $table->string('transaction_number', 50)->nullable();
            $table->string('notes', 500)->nullable()->nullable();
            $table->enum('status', ['Initialized', 'Completed', 'Failed'])->default('Initialized')->index();
            $table->date('date')->nullable();
            $table->dateTime('created_at')->useCurrent()->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->enum('created_by_user_type', ['Admin', 'Tour Operator', 'Affiliate'])->default('Tour Operator');
            $table->dateTime('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable()->default(0);
            $table->enum('deleted_by_user_type', ['Admin', 'Tour Operator', 'Affiliate'])->default('Tour Operator');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tour_booking_tips');
    }
};
