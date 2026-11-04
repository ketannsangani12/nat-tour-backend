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
        Schema::create('transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tour_booking_id')->nullable()->index('transactions_tour_booking_id');
            $table->decimal('amount', 7)->default(0)->comment('Negative amount means refund');
            $table->double('fee_amount', 8, 2)->nullable();
            $table->string('transaction_number', 50)->nullable();
            $table->string('application_fee_id')->nullable();
            $table->string('via', 100)->nullable()->index()->comment('Payment gateway name');
            $table->tinyInteger('payment_type')->index();
            $table->text('response_init');
            $table->text('response_end');
            $table->dateTime('response_end_datetime')->nullable();
            $table->enum('status', ['Initialized', 'Completed', 'Failed', 'Refund'])->default('Initialized');
            $table->string('visitor', 45)->nullable()->index();
            $table->string('request_from')->nullable();
            $table->dateTime('created_at')->useCurrent()->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index('created_by');
            $table->enum('created_by_user_type', ['Admin', 'Tour Operator', 'Customer', 'Affiliate'])->default('Admin');
            $table->dateTime('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable()->default(0)->index('deleted_by');
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
        Schema::dropIfExists('transactions');
    }
};
