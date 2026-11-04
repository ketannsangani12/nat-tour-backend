<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('settings')->where('key', 'DEFAULT_BOOKING_SOURCE')->exists()) {
            DB::table('settings')->insert([
                'key'          => 'DEFAULT_BOOKING_SOURCE',
                'value'        => '',
                'order_number' => 100,
                'comment'      => 'Global default booking source when the logged-in user has no personal default set',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'DEFAULT_BOOKING_SOURCE')->delete();
    }
};
