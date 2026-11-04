<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('permit_fee_refund_queue', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('package_id');
            $table->year('year');
            $table->date('tour_date');
            $table->integer('tourists');
            $table->decimal('total_amount', 10, 2);
            $table->json('rate_groups'); // Store rate group details
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamps();
            
            $table->index(['booking_id', 'status']);
            $table->index(['package_id', 'year']);
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('permit_fee_refund_queue');
    }
};