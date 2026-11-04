<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('settings')->where('key', 'DISPUTE_REVERSAL')->exists();

        if (!$exists) {
            DB::table('settings')->insert([
                'key'          => 'DISPUTE_REVERSAL',
                'value'        => 0,
                'order_number' => 48,
                'comment'      => 'Enable or disable automatic dispute transfer reversal from connected accounts',
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'DISPUTE_REVERSAL')->delete();
    }
};
