<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE payout_approval_logs MODIFY COLUMN action ENUM('created','approved','rejected','executed','failed','cancelled') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE payout_approval_logs MODIFY COLUMN action ENUM('created','approved','rejected','executed','failed') NOT NULL");
    }
};
