<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Bookings made before the Category field existed have NULL category_id/
     * category on their tour_booking_addon_fields rows. Backfill them to
     * "Default" so category-wise reports never show an uncategorized bucket.
     */
    public function up(): void
    {
        $defaultCategoryId = DB::table('addon_categories')->where('name', 'Default')->value('id');

        if (!$defaultCategoryId) {
            return;
        }

        DB::table('tour_booking_addon_fields')
            ->whereNull('category_id')
            ->update([
                'category_id' => $defaultCategoryId,
                'category_name' => 'Default',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not reversible — we can't distinguish rows that were genuinely
        // backfilled from ones that were legitimately written as Default.
    }
};
