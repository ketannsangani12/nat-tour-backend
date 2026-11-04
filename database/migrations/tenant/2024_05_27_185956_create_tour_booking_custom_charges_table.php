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
        if(!Schema::hasTable('tour_booking_custom_charges')){
            Schema::create('tour_booking_custom_charges', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->biginteger('tour_booking_id')->nullable()->unsigned()->comment('foreign key tour_bookings_master');
                $table->foreign('tour_booking_id')->references('id')->on('tour_bookings_master')->onDelete('cascade');
                $table->decimal('amount', 7)->default(0);
                $table->string('transaction_id')->nullable();
                $table->text('description')->nullable();
                $table->text('payment_method')->nullable()->comment('Payment method of the payment or refund');
                $table->string('status')->default("open");
                $table->unsignedBigInteger('operation_by')->nullable()->default(0);
                $table->enum('operation_by_user_type', ['Admin', 'Tour Operator', 'Customer', 'Affiliate'])->default('Admin');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tour_booking_custom_charges');
    }
};
