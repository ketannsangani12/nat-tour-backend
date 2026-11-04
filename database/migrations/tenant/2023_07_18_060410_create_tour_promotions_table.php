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
        Schema::create('tour_promotions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tour_operator_id')->nullable()->index('tour_promotions_tour_operator_id');
            $table->string('code', 20)->nullable()->index('code');
            $table->date('start_date')->nullable()->index('start_date');
            $table->date('end_date')->nullable()->index('end_date');
            $table->unsignedInteger('max_allowed_usages')->default(0);
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->enum('discount_value_type', ['Percent', 'Fixed Money'])->default('Percent');
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
        Schema::dropIfExists('tour_promotions');
    }
};
