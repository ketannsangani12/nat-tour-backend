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
        Schema::create('invoices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('uuid', 36);
            $table->unsignedBigInteger('tour_operator_id')->index('invoices_tour_operator_id_foreign');
            $table->unsignedBigInteger('customer_id')->index('invoices_customer_id_foreign');
            $table->string('invoice_number')->index();
            $table->string('reference_invoice', 100)->nullable();
            $table->date('from_date')->index();
            $table->date('to_date')->index();
            $table->decimal('amount', 10, 2)->default(0)->comment('The unpaid amount of affiliate');
            $table->date('due_date')->index();
            $table->tinyInteger('email_sent')->default(0)->comment('Count of the email sent to the affiliate');
            $table->enum('status', ['draft', 'sent', 'viewed', 'approved', 'partial', 'paid', 'overdue', 'unpaid', 'cancelled'])->default('draft')->index()->comment('draft, sent, viewed, approved, partial, paid, overdue, unpaid, cancelled');
            $table->string('external_status', 191)->nullable();
            $table->string('external_customer_id', 191)->nullable();
            $table->string('external_invoice_id', 191)->nullable();
            $table->string('external_payment_link', 191)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable()->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('invoices');
    }
};
