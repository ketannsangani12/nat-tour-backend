<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_booking_payment_link_items', function (Blueprint $table) {
            if (!Schema::hasColumn('tour_booking_payment_link_items', 'status')) {
                $table->enum('status', ['Unpaid', 'Paid', 'Cancelled', 'Expired'])
                      ->default('Unpaid')
                      ->after('amount');
            }

            if (!Schema::hasColumn('tour_booking_payment_link_items', 'created_by_user_type')) {
                $table->enum('created_by_user_type', ['Admin', 'Tour Operator', 'Affiliate'])
                      ->nullable()
                      ->after('created_by');
            }

            if (!Schema::hasColumn('tour_booking_payment_link_items', 'updated_by_user_type')) {
                $table->enum('updated_by_user_type', ['Admin', 'Tour Operator', 'Affiliate'])
                      ->nullable()
                      ->after('updated_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tour_booking_payment_link_items', function (Blueprint $table) {
            $columns = array_filter(
                ['status', 'created_by_user_type', 'updated_by_user_type'],
                fn ($column) => Schema::hasColumn('tour_booking_payment_link_items', $column)
            );

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
