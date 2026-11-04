<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tour_booking_addon_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tour_booking_id')->nullable()->index();
            $table->unsignedBigInteger('form_id')->nullable()->index();
            $table->unsignedBigInteger('field_id')->nullable()->index();
            $table->string('field_label')->nullable();
            $table->unsignedBigInteger('option_id')->nullable()->index();
            $table->string('option_label')->nullable();
            $table->unsignedBigInteger('rule_id')->nullable()->index();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->text('value')->nullable()->comment('Store user-entered value for text/textarea/number fields'); 
            $table->unsignedBigInteger('pax_id')->nullable()->index('tour_bookings_transact_pax_id')->comment('Reference to tour_bookings_transact table');
            $table->string('pax_index')->nullable();
            $table->enum('unit_type', ['one_time', 'price_per_unit', 'price_per_pax'])->nullable();
            $table->integer('unit')->default(0)->comment('Unit/quantity selected for the addon');
            $table->json('applied_rule_snapshot')->nullable();
            $table->dateTime('operation_at')->nullable()->index();
            $table->unsignedBigInteger('operation_by')->nullable()->default(0);
            $table->enum('operation_by_user_type', ['Admin', 'Tour Operator', 'Customer', 'Affiliate'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tour_booking_addon_fields');
    }
};
