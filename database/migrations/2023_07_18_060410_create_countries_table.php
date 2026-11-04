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
        if (Schema::hasTable('countries')) {
            return;
        }

        Schema::create('countries', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 80)->nullable()->unique('name');
            $table->string('code', 10)->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('currency_symbol', 5)->nullable();
            $table->enum('status', ['Inactive', 'Active'])->default('Active');
            $table->dateTime('created_at')->useCurrent()->nullable();
            $table->unsignedInteger('created_by')->nullable()->index('countries_created_by');
            $table->enum('created_by_user_type', ['Admin', 'Tour Operator', 'Affiliate'])->default('Admin');
            $table->dateTime('updated_at')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->enum('updated_by_user_type', ['Admin', 'Tour Operator', 'Affiliate'])->default('Admin');
            $table->dateTime('deleted_at')->nullable();
            $table->unsignedInteger('deleted_by')->nullable()->default(0);
            $table->enum('deleted_by_user_type', ['Admin', 'Tour Operator', 'Affiliate'])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('countries');
    }
};
