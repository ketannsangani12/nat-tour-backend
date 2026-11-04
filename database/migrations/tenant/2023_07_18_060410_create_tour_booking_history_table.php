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
        Schema::create('tour_booking_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tour_booking_id')->nullable()->index('tour_booking_history_tour_booking_id_foreign');
            $table->unsignedBigInteger('transaction_id')->nullable()->index();
            $table->unsignedSmallInteger('tourists')->default(0);
            $table->enum('operation', ['Note', 'Comments', 'Payment', 'Refund', 'Cancelled', 'Email', 'Check-in', 'Booked By', 'Rescheduled', 'Waiver', 'Tip', 'Added', 'Update', 'Restore', 'Customer Info Update', 'Payment Type Update', 'Error', 'Reference Booking', 'Card Error', 'Split Booking Original', 'Split Booking'])->default('Note')->index();
            $table->decimal('ticket_cost', 10, 2)->nullable()->default(0)->comment('Base amount of the payment or refund');
            $table->decimal('discount', 10, 2)->nullable()->default(0)->comment('Discount amount of the payment');
            $table->decimal('permit_fees', 10, 2)->nullable()->default(0)->comment('Permit fee amount of the payment or refund');
            $table->decimal('processing_fees', 10, 2)->nullable()->default(0)->comment('Processing fee amount of the payment or refund');
            $table->decimal('platform_fees', 10, 2)->nullable()->default(0)->comment('Platform fee amount of the payment or refund');
            $table->decimal('change_amount', 10, 2)->default(0)->comment('Change amount for cash payment');
            $table->string('card_last_4_digit', 191)->nullable()->index()->comment('Card last 4 digit of the payment or refund');
            $table->text('payment_type')->nullable()->comment('Payment type of the payment or refund');
            $table->text('payment_method')->nullable()->comment('Payment method of the payment or refund');
            $table->tinyInteger('is_processed')->default(0)->comment('Update this to 1 when command executed');
            $table->text('details')->nullable();
            $table->dateTime('operation_at')->nullable()->index();
            $table->unsignedBigInteger('operation_by')->nullable()->default(0);
            $table->enum('operation_by_user_type', ['Admin', 'Tour Operator', 'Customer', 'Affiliate'])->default('Admin');
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tour_booking_history');
    }
};
