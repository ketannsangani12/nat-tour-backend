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
        Schema::create('tour_bookings_master', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('viator_bookingRef', 191)->nullable();
            $table->unsignedBigInteger('tour_package_id')->nullable()->index('tour_bookings_master_tour_package_id');
            $table->unsignedBigInteger('tour_slot_id')->nullable()->index('tour_bookings_master_tour_slot_time_id');
            $table->unsignedBigInteger('tour_promotion_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable()->index('tour_bookings_master_customer_id');
            $table->string('name', 80);
            $table->string('email', 50);
            $table->string('phone_number', 30);
            $table->unsignedSmallInteger('tourists')->default(0);
            $table->decimal('service_commission', 10, 2)->nullable()->default(0);
            $table->text('comments');
            $table->text('note');
            $table->dateTime('note_datetime')->nullable();
            $table->string('reference_booking', 100)->nullable();
            $table->enum('mask_required', ['Yes', 'No'])->default('Yes');
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('discount2_value', 10, 2)->default(0)->comment('Discount given by admin or tour operator (staff)');
            $table->decimal('discount2_percentage', 10, 2)->default(0)->comment('It will be Zero in case of discount of fixed money is given');
            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('booking_total', 10, 2)->default(0);
            $table->string('stripe_paymentintent_id')->nullable()->index();
            $table->text('liability_waiver_signing')->comment('JSON Obj: { sign_date_time: , tourists: [ { name, age }, ... ] }');
            $table->enum('status', ['Booked', 'Completed', 'Cancelled'])->default('Booked')->index('status');
            $table->dateTime('created_at')->useCurrent()->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index('created_by');
            $table->enum('created_by_user_type', ['Admin', 'Tour Operator', 'Customer', 'Affiliate'])->default('Customer');
            $table->dateTime('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable()->index('updated_by');
            $table->enum('updated_by_user_type', ['Admin', 'Tour Operator', 'Affiliate'])->default('Tour Operator');
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
        Schema::dropIfExists('tour_bookings_master');
    }
};
