<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Populate tenant_name for existing records
        DB::connection('mysql')->statement("
            UPDATE switch_companies sc
            JOIN tenants t ON t.id = sc.tenant_id
            SET sc.tenant_name = t.name
            WHERE sc.tenant_name IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Set tenant_name back to null
        DB::connection('mysql')->table('switch_companies')
            ->update(['tenant_name' => null]);
    }
};
