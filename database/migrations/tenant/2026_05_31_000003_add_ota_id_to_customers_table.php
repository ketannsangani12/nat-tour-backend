<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // . Must match the platform key in config/auth.php octo_auth.
            // Set once at creation — never updated — so display name renames do not break OCTO API integration.
            // Active uniqueness enforced at the application layer (AffiliateRequest), not by a DB unique constraint,
            // so soft-deleted rows retain their slug for historical reference without blocking re-registration.
            $table->string('ota_id', 100)
                ->nullable()
                ->index()
                ->after('is_ota')
                ->comment(
                    'OTA reseller slug (e.g. headout, viator)'
                );
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['ota_id']);
            $table->dropColumn('ota_id');
        });
    }
};
