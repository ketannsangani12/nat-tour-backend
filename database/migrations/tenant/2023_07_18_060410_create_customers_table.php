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
        Schema::create('customers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tour_operator_id')->nullable();
            $table->string('stripe_id', 191)->nullable();
            $table->string('name', 80)->nullable()->index();
            $table->string('email', 50)->nullable()->index();
            $table->string('password')->nullable();
            $table->string('phone_number', 30);
            $table->enum('is_subscribed', ['No', 'Yes'])->default('No')->index('customers_is_subscribed');
            $table->enum('is_price_show', ['No', 'Yes'])->default('No');
            $table->enum('is_affiliate', ['Yes', 'No'])->default('No')->index();
            $table->enum('status', ['Inactive', 'Active'])->default('Active')->index('status');
            $table->boolean('external_invoice_enabled')->default(false);
            $table->string('external_customer_id', 191)->nullable();
            $table->integer('duration')->default(0);
            $table->integer('day_hours')->nullable();
            $table->rememberToken()->nullable(false);
            $table->dateTime('created_at')->useCurrent()->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index('customers_created_by');
            $table->enum('created_by_user_type', ['Admin', 'Tour Operator', 'Affiliate'])->default('Tour Operator');
            $table->dateTime('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable()->index('customers_updated_by');
            $table->enum('updated_by_user_type', ['Admin', 'Tour Operator', 'Affiliate'])->default('Tour Operator');
            $table->dateTime('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable()->index('customers_deleted_by')->default(0);
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
        Schema::dropIfExists('customers');
    }
};
