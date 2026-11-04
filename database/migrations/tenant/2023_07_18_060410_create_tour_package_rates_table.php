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
        Schema::create('tour_package_rates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('affiliate_id')->nullable()->index('tour_package_rates_affiliate_id_foreign');
            $table->unsignedBigInteger('tour_package_id')->nullable()->index('tour_package_rates_tour_package_id');
            $table->string('rate_for', 50)->default('')->comment('Examples: Infant, Child, Teenager, Adult, Senior Citizen');
            $table->unsignedTinyInteger('age_from')->default(0)->index();
            $table->unsignedTinyInteger('age_to')->default(0)->index();
            $table->decimal('rate', 10, 2)->default(0);
            $table->decimal('permit_fee', 10, 2)->default(0);
            $table->text('description');
            $table->decimal('additional_tax_percentage', 10, 2)->default(0);
            $table->string('processing_charge_percentage', 250)->nullable();
            $table->decimal('processing_charge', 10, 2)->default(0);
            $table->decimal('additional_charge', 10, 2)->default(0);
            $table->decimal('margin', 10, 2)->default(0)->index();
            $table->bigInteger('scheduling_year')->nullable()->index();
            $table->enum('status', ['Inactive', 'Active'])->default('Active')->index('status');
            $table->tinyInteger('orderby')->index();
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
        Schema::dropIfExists('tour_package_rates');
    }
};
