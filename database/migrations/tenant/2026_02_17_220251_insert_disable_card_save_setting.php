<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('settings')
            ->where('key', 'DISABLE_CARD_SAVE')
            ->exists();

        if (!$exists) {
            DB::table('settings')->insert([
                'key'          => 'DISABLE_CARD_SAVE',
                'value'        => '0',
                'order_number' => 41,
                'comment'      => 'No - Disable, Yes - Enable : Enable DISABLE CARD SAVE',
                'input_type'   => null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'DISABLE_CARD_SAVE')
            ->delete();
    }
};