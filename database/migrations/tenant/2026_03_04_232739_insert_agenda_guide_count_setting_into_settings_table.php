<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $exists = DB::table('settings')
            ->where('key', 'AGENDA_GUIDE_COUNT')
            ->exists();

        if (!$exists) {
            DB::table('settings')->insert([
                'key'          => 'AGENDA_GUIDE_COUNT',
                'value'        => '0',
                'order_number' => 42,
                'comment'      => 'No - Disable, Yes - Enable : Enable AGENDA_GUIDE_COUNT',
                'input_type'   => null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'AGENDA_GUIDE_COUNT')
            ->delete();
    }
};