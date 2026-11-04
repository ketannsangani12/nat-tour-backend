<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Existing top-level add-on fields created before the Category field existed
     * have category_id = NULL. Backfill them to the "Default" category so they
     * behave the same as new fields (which are pre-selected to Default) and so
     * category-wise reporting has no uncategorized gaps. Child fields are left
     * untouched — they inherit their parent's category at read time.
     */
    public function up(): void
    {
        $defaultCategoryId = DB::table('addon_categories')->where('name', 'Default')->value('id');

        if (!$defaultCategoryId) {
            return;
        }

        DB::table('addon_fields')
            ->whereNull('category_id')
            ->whereNull('parent_field_id')
            ->update(['category_id' => $defaultCategoryId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not reversible — we can't distinguish fields that were genuinely
        // backfilled from ones an admin later explicitly set to Default.
    }
};
