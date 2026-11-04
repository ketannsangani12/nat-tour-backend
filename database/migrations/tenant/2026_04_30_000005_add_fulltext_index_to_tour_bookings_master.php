<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = collect(DB::select("SHOW INDEX FROM tour_bookings_master WHERE Key_name = 'ft_tbm_search'"))->isNotEmpty();
        if (!$exists) {
            DB::statement('ALTER TABLE tour_bookings_master ADD FULLTEXT INDEX ft_tbm_search (name, email, note, comments)');
        }
    }

    public function down(): void
    {
        $exists = collect(DB::select("SHOW INDEX FROM tour_bookings_master WHERE Key_name = 'ft_tbm_search'"))->isNotEmpty();
        if ($exists) {
            DB::statement('ALTER TABLE tour_bookings_master DROP INDEX ft_tbm_search');
        }
    }
};
