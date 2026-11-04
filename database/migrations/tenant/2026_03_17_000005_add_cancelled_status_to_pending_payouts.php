<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE pending_payouts MODIFY COLUMN status ENUM('pending_approval','approved','rejected','executed','failed','cancelled') DEFAULT 'pending_approval'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE pending_payouts MODIFY COLUMN status ENUM('pending_approval','approved','rejected','executed','failed') DEFAULT 'pending_approval'");
    }
};
