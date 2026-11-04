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
        Schema::create('tour_operators', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('city_id')->nullable()->index('tour_operators_city_id');
            $table->string('name', 100)->nullable()->index();
            $table->string('shortdesc', 50)->nullable();
            $table->string('email', 50)->nullable();
            $table->string('phone_number', 30)->nullable();
            $table->string('website', 200)->nullable();
            $table->text('description')->nullable();
            $table->text('cancellation_policy')->nullable();
            $table->string('statement_descriptor', 21)->nullable();
            $table->string('card_reader_prefix', 21)->nullable();
            $table->text('logo')->nullable();
            $table->text('logo_navigation')->nullable()->comment('Menu Navigation Logo');
            $table->text('logo_print')->nullable()->comment('Print Logo');
            $table->text('address')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('timezone')->default('America/Phoenix');
            $table->text('social_media_urls')->nullable()->comment('JSON Object. Example: { Facebook: https://facebook.com/exmaple, Twitter: https://twitter.com/example }');
            $table->text('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->text('seo_keywords')->nullable();
            $table->longText('reports')->nullable();
            $table->string('checkins', 191)->nullable()->default('["N","1","2","A"]');
            $table->integer('is_affiliate')->nullable()->index();
            $table->decimal('service_commission_percentage', 10, 8)->nullable()->default(0);
            $table->decimal('stripe_commission_percentage', 10, 8)->default(0)->comment('For non affiliate only, e.g. 6.02');
            $table->decimal('affiliate_processing_percentage', 10, 8)->default(0)->comment('For affiliate only, e.g. 2.90');
            $table->decimal('stripe_affiliate_percentage', 10, 8)->default(0)->comment('For affiliate only, e.g. 2.90');
            $table->enum('status', ['Inactive', 'Active'])->default('Active');
            $table->string('stripe_destination_id', 100)->nullable();
            $table->boolean('stripe_invoice_enabled')->default(false);
            $table->string('tripadvisor_supplier_id', 10)->nullable();
            $table->string('tripadvisor_reseller_id', 10)->nullable();
            $table->dateTime('created_at')->useCurrent()->nullable()->index();
            $table->unsignedInteger('created_by')->nullable()->index('created_by');
            $table->dateTime('updated_at')->nullable();
            $table->unsignedInteger('updated_by')->nullable()->index('updated_by');
            $table->enum('updated_by_user_type', ['Admin', 'Tour Operator'])->default('Tour Operator');
            $table->dateTime('deleted_at')->nullable();
            $table->unsignedInteger('deleted_by')->nullable()->default(0)->index('deleted_by');
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
        Schema::dropIfExists('tour_operators');
    }
};
