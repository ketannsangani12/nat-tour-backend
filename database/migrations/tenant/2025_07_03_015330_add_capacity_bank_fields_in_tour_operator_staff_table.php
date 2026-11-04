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
        Schema::table('tour_operator_staff', function (Blueprint $table) {
            $table->integer('capacity')
                ->default(0)
                ->after('status')
                ->comment('The maximum number of customers the staff can handle at a time');
            $table->string('stripe_account_id', 191)
                ->nullable()
                ->after('capacity')
                ->comment('Stripe account ID for the staff member, used for payment processing');
            $table->string('bank_account_number', 191)
                ->nullable()
                ->after('stripe_account_id');
            $table->string('bank_routing_number', 191)
                ->nullable()
                ->after('bank_account_number');
            $table->string('account_holder_name', 255)
                ->nullable()
                ->after('bank_routing_number');
            $table->string('bank_account_type', 100)
                ->nullable()
                ->after('bank_routing_number');
            $table->enum('payout_primary_method', ['Connected Account', 'Bank Account'])->default('Connected Account')->after('bank_account_type')
                ->comment('Primary method for payouts, either through a connected Stripe account or directly to a bank account');    
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_operator_staff', function (Blueprint $table) {
            //
        });
    }
};
