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
        Schema::create('tour_bookings_transact', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tour_booking_id')->nullable()->index('tour_booking_transact_tour_booking_id');
            $table->unsignedBigInteger('tour_package_rate_id')->nullable()->index('tour_booking_transact_tour_package_rate_id');
            $table->unsignedInteger('tourists')->default(0);
            $table->decimal('processing_charge', 10, 2)->default(0);
            $table->decimal('additional_tax_percentage', 10, 2)->default(0);
            $table->decimal('processing_charge_percentage', 10, 2)->nullable()->default(0);
            $table->decimal('nat_fees_percentage', 10, 8)->default(0);
            $table->decimal('stripe_fees_percentage', 10, 8)->default(0);
            $table->decimal('permit_fee', 10, 2)->default(0);
            $table->decimal('additional_charge', 10, 2)->default(0);
            $table->decimal('state_tax', 10, 2)->default(0);
            $table->decimal('rate', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->text('refund_permitfee_status')->nullable();
            $table->text('check_in_status')->comment('JSON Format: [ { status: $1, dateTime: 0000-00-00 00:00:00 }, { ... } ] WHERE $1 can be "N" = Not Checked In, 1 = 1st Check In, 2 = 2nd Check In, "A" = Absent');
            $table->dateTime('created_at')->useCurrent()->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index('created_by');
            $table->enum('created_by_user_type', ['Admin', 'Tour Operator', 'Customer', 'Affiliate'])->default('Admin');
            $table->dateTime('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable()->index('updated_by');
            $table->enum('updated_by_user_type', ['Admin', 'Tour Operator', 'Affiliate'])->default('Tour Operator');
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
        Schema::dropIfExists('tour_bookings_transact');
    }
};
