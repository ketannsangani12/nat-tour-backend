<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('settings')->where('key', 'AUTO_PAYOUT')->exists();

        if (!$exists) {
            DB::table('settings')->insert([
                'key' => 'AUTO_PAYOUT',
                'value' => 0,
                'order_number' => 47,
                'comment' => 'No - Disable, Yes - Enable : Auto Payout',
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'AUTO_PAYOUT')->delete();
    }
};
