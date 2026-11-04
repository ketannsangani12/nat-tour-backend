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
        Schema::create('tour_packages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tour_operator_id')->nullable()->index('tour_packages_tour_operator_id');
            $table->string('name', 100)->default('')->index();
            $table->string('email')->nullable();
            $table->string('from_email')->nullable();
            $table->string('api_key')->nullable();
            $table->string('stripe_destination_id')->nullable();
            $table->integer('overbook_seats')->default(50);
            $table->text('short_description');
            $table->text('long_description');
            $table->unsignedTinyInteger('hours')->default(0)->index('duration');
            $table->unsignedTinyInteger('minutes')->default(0);
            $table->integer('duration')->nullable()->default(0);
            $table->integer('day_hours')->nullable();
            $table->text('things_to_bring');
            $table->text('important_notes')->comment('JSON array, for example: ["Torch","Raincoat",...]');
            $table->text('seo_title');
            $table->text('seo_description');
            $table->text('seo_keywords');
            $table->longText('waiver_content')->nullable();
            $table->enum('status', ['Inactive', 'Active'])->default('Active');
            $table->integer('Order_by')->index();
            $table->string('viator_tags', 191)->nullable();
            $table->string('destinationId', 191)->nullable();
            $table->string('productCode', 191)->nullable();
            $table->text('viator_flags')->nullable();
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
        Schema::dropIfExists('tour_packages');
    }
};
