<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
	   DB::statement("ALTER TABLE `cancellation_policies` CHANGE `refund_basis` `refund_basis` ENUM('ticket_cost', 'no_refund') default 'no_refund' not null;");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
		DB::statement("ALTER TABLE `cancellation_policies` CHANGE `refund_basis` `refund_basis` ENUM('ticket_cost', 'no_refund') default 'no_refund' not null;");
	}
};
